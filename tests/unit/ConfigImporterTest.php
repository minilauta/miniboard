<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ConfigImporter;
use minichan\core\ConfigImportException;
use minichan\core\ConfigRepository;
use minichan\core\ConfigSchema;
use minichan\core\ConfigScope;
use minichan\core\ConfigSource;
use minichan\core\ConfigStore;

require_once __ROOT__ . '/core/config_importer.php';

class ConfigImporterTest extends TestCase
{
	private string $tmpDir;

	protected function setUp(): void
	{
		$this->tmpDir = sys_get_temp_dir() . '/miniboard_test_config_' . bin2hex(random_bytes(4));
		mkdir($this->tmpDir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->tmpDir . '/*') as $file) {
			unlink($file);
		}
		rmdir($this->tmpDir);
	}

	private static function fixture(string $name): string
	{
		return dirname(__DIR__) . '/fixtures/' . $name;
	}

	/**
	 * Returns the config table rows of source values, as written by an import.
	 */
	private static function rows(array $values): array
	{
		$rows = [];
		foreach ($values as $board_id => $keys) {
			foreach ($keys as $key => $value) {
				$type = ConfigSchema::get($key)->type;
				$rows[] = ['board_id' => (string) $board_id, 'key' => $key, 'type' => $type->value, 'value' => $type->encode($value)];
			}
		}

		return $rows;
	}

	private function write_legacy(string $php): string
	{
		$path = $this->tmpDir . '/config.php';
		file_put_contents($path, "<?php\n" . $php);
		return $path;
	}

	private function minimal_board(): string
	{
		return "'name' => 'Random', 'desc' => '', 'type' => 'normal'";
	}

	public function legacy_provider(): array
	{
		return [
			'repo config, hoisted' => ['legacy_config_repo.php', true],
			'repo config, not hoisted' => ['legacy_config_repo.php', false],
			'k8s config, hoisted' => ['legacy_config_k8s.php', true],
			'k8s config, not hoisted' => ['legacy_config_k8s.php', false],
		];
	}

	/**
	 * The import must be exact: every legacy value resolves to the same value from the database.
	 *
	 * @dataProvider legacy_provider
	 */
	public function test_import_is_equivalent_to_the_legacy_config(string $fixture, bool $hoist): void
	{
		$constants = ConfigImporter::read_legacy_constants(self::fixture($fixture));
		$source = ConfigImporter::read(self::fixture($fixture), $hoist);
		$store = new ConfigStore(self::rows($source->values));

		$this->assertSame([], $source->warnings);
		$this->assertSame([], $store->errors());

		// site-wide values, inheritable ones resolve to the same value on every board
		foreach (ConfigSchema::keys() as $key => $definition) {
			if ($definition->legacy === null || $definition->legacy === 'MB_BOARDS') {
				continue;
			}

			$this->assertSame($constants[$definition->legacy], $store->get($key), "{$definition->legacy} = {$key}");
			if ($definition->scope === ConfigScope::Inheritable) {
				foreach (array_keys($constants['MB_BOARDS']) as $board_id) {
					$this->assertSame($constants[$definition->legacy], $store->board($board_id)[$key], "{$definition->legacy} = /{$board_id}/ {$key}");
				}
			}
		}

		// boards, in the same order
		$this->assertSame(array_keys($constants['MB_BOARDS']), array_keys($store->boards()));
		foreach ($constants['MB_BOARDS'] as $board_id => $legacy_board) {
			$board = $store->board($board_id);
			foreach ($legacy_board as $key => $value) {
				$this->assertSame($value, $board[$key], "MB_BOARDS['{$board_id}']['{$key}']");
			}
		}
	}

	public function test_hoisting_moves_shared_board_values_site_wide(): void
	{
		$source = ConfigImporter::read(self::fixture('legacy_config_repo.php'), true);

		foreach (['alwaysnoko', 'threads_per_page', 'threads_per_catalog_page', 'posts_per_preview', 'truncate', 'anonymous', 'hidden', 'flags'] as $key) {
			$this->assertArrayHasKey($key, $source->values['global'], $key);
			$this->assertArrayNotHasKey($key, $source->values['b'], $key);
		}

		// values that differ between boards stay per board
		foreach (['maxkb', 'nsfw', 'mime_ext_types', 'req_role', 'text'] as $key) {
			$this->assertArrayNotHasKey($key, $source->values['global'], $key);
			$this->assertArrayHasKey($key, $source->values['b'], $key);
		}

		$this->assertSame(['main' => 10, 'b' => 20, 'a' => 30, 'i' => 40, 'text' => 50, 'mod' => 60], array_map(fn($b) => $b['position'], array_diff_key($source->values, ['global' => 1])));
	}

	public function test_defaults_are_site_wide_only(): void
	{
		$defaults = ConfigImporter::defaults();

		$this->assertSame(['global'], array_keys($defaults->values));
		$this->assertSame(array_keys(ConfigSchema::keys_of(ConfigScope::Global, ConfigScope::Inheritable)), array_keys($defaults->values['global']));
		foreach ($defaults->values['global'] as $key => $value) {
			$this->assertSame(ConfigSchema::get($key)->default, $value, $key);
			$this->assertNull(ConfigSchema::validate_write('global', $key, $value), $key);
		}

		// a new install has no boards, the defaults alone resolve without errors
		$store = new ConfigStore(self::rows($defaults->values));
		$this->assertSame([], $store->errors());
		$this->assertSame([], $store->boards());
		$this->assertFalse($store->is_empty());
	}

	public function test_legacy_import_with_overwrite_over_the_defaults_is_exact(): void
	{
		// e.g. a dev database seeded with the defaults, then the old example boards imported
		$source = ConfigImporter::read(self::fixture('legacy_config_k8s.php'));
		$rows = self::rows(ConfigImporter::defaults()->values);

		$diff = ConfigImporter::diff($rows, $source);
		$this->assertNotEmpty($diff->differs, 'the k8s config differs from the defaults, e.g. threads_per_catalog_page');

		$values = ConfigImporter::defaults()->values;
		foreach ($diff->inserts as $row) {
			$values[$row['board_id']][$row['key']] = $row['value'];
		}
		foreach ($diff->differs as $row) {
			$values[$row['board_id']][$row['key']] = $row['new'];
		}
		$this->assertTrue(ConfigImporter::diff(self::rows($values), $source)->is_empty());
	}

	public function test_json_roundtrip(): void
	{
		$source = ConfigImporter::read(self::fixture('legacy_config_repo.php'));
		$path = $this->tmpDir . '/export.json';
		file_put_contents($path, ConfigImporter::to_json($source->values));

		$this->assertSame($source->values, ConfigImporter::read($path)->values);
	}

	public function test_export_orders_all_first_then_boards_by_position(): void
	{
		$json = ConfigImporter::to_json([
			'b' => ['position' => 20, 'name' => 'Random'],
			'a' => ['position' => 10, 'name' => 'Anime'],
			'global' => ['site_name' => 'Site'],
		]);

		$this->assertSame(['global', 'a', 'b'], array_keys(json_decode($json, true)));
		$this->assertSame(['name', 'position'], array_keys(json_decode($json, true)['a']));
	}

	public function test_missing_and_unknown_keys_are_warnings(): void
	{
		$path = $this->write_legacy("
			define('MB_SITE_NAME', 'Old site');
			define('MB_CUSTOM_THING', 1);
			define('MB_ROLE_ADMIN', 2);
			define('MB_TIMEFRAME', 3600);
			define('MB_BOARDS', ['b' => ['id' => 'b', " . $this->minimal_board() . ", 'unknown_board_key' => 1]]);
		");
		$source = ConfigImporter::read($path);
		$warnings = implode("\n", $source->warnings);

		$this->assertSame('Old site', $source->values['global']['site_name']);
		$this->assertSame(ConfigSchema::get('delay_thread')->default, $source->values['global']['delay_thread']);
		$this->assertStringContainsString('MB_DELAY_THREAD is not set', $warnings);
		$this->assertStringContainsString('MB_CUSTOM_THING is not a setting', $warnings);
		$this->assertStringContainsString("MB_BOARDS['b']['unknown_board_key'] is not a setting", $warnings);
		$this->assertStringContainsString("MB_BOARDS['b']['maxkb'] is not set", $warnings);
		$this->assertStringNotContainsString('MB_ROLE_ADMIN', $warnings);
		$this->assertStringNotContainsString('MB_TIMEFRAME', $warnings);
	}

	public function test_wrong_types_fail(): void
	{
		$path = $this->write_legacy("
			define('MB_DELAY', '15');
			define('MB_BOARDS', ['b' => [" . $this->minimal_board() . ", 'maxkb' => '24000']]);
		");

		try {
			ConfigImporter::read($path);
			$this->fail('import must fail');
		} catch (ConfigImportException $e) {
			$errors = implode("\n", $e->errors);
			$this->assertStringContainsString('delay must be of type int, got string', $errors);
			$this->assertStringContainsString('/b/: maxkb must be of type int, got string', $errors);
		}
	}

	public function test_reserved_board_id_fails(): void
	{
		$path = $this->write_legacy("define('MB_BOARDS', ['global' => [" . $this->minimal_board() . "]]);");

		$this->expectException(ConfigImportException::class);
		$this->expectExceptionMessage("board ID 'global' is reserved");
		ConfigImporter::read($path);
	}

	public function test_mismatching_board_id_fails(): void
	{
		$path = $this->write_legacy("define('MB_BOARDS', ['b' => ['id' => 'a', " . $this->minimal_board() . "]]);");

		$this->expectException(ConfigImportException::class);
		$this->expectExceptionMessage("has a different id 'a'");
		ConfigImporter::read($path);
	}

	public function test_failing_legacy_config_fails(): void
	{
		$path = $this->write_legacy("throw new Exception('broken config');");

		$this->expectException(ConfigImportException::class);
		$this->expectExceptionMessage('broken config');
		ConfigImporter::read($path);
	}

	public function test_legacy_output_is_a_warning(): void
	{
		$path = $this->write_legacy("
			trigger_error('something odd', E_USER_WARNING);
			define('MB_BOARDS', ['b' => [" . $this->minimal_board() . "]]);
		");

		$this->assertStringContainsString('something odd', implode("\n", ConfigImporter::read($path)->warnings));
	}

	public function test_json_errors(): void
	{
		$cases = [
			'{' => 'invalid JSON',
			'{"global": {"unknown_key": {"type": "int", "value": 1}}}' => 'unknown config key',
			'{"global": {"delay": {"type": "string", "value": "15"}}}' => 'but the key is int',
			'{"global": {"delay": {"value": 15}}}' => "expected an object with 'type' and 'value'",
			'{"b": {"site_name": {"type": "string", "value": "x"}}}' => "can only be set site-wide",
			'{"global": {"plugins": {"type": "json", "value": ["../x"]}}}' => 'plugins must be a list of names',
		];

		foreach ($cases as $json => $error) {
			try {
				ConfigImporter::from_json($json);
				$this->fail("import of {$json} must fail");
			} catch (ConfigImportException $e) {
				$this->assertStringContainsString($error, $e->getMessage(), $json);
			}
		}
	}

	public function test_diff_inserts_differs_and_unchanged(): void
	{
		$rows = self::rows([
			'global' => ['site_name' => 'Old', 'delay' => 15],
			'b' => ['name' => 'Random', 'desc' => '', 'type' => 'normal', 'position' => 10],
			'x' => ['name' => 'Extra', 'desc' => '', 'type' => 'normal', 'position' => 20],
		]);
		$rows[] = ['board_id' => 'global', 'key' => 'delay_thread', 'type' => 'string', 'value' => 'invalid'];

		$source = new ConfigSource([
			'global' => ['site_name' => 'New', 'delay' => 15, 'delay_thread' => 60, 'cloudflare' => true],
			'b' => ['name' => 'Random'],
		], []);
		$diff = ConfigImporter::diff($rows, $source);

		$this->assertSame([['board_id' => 'global', 'key' => 'cloudflare', 'value' => true]], $diff->inserts);
		$this->assertSame([
			['board_id' => 'global', 'key' => 'site_name', 'old' => 'Old', 'new' => 'New'],
			['board_id' => 'global', 'key' => 'delay_thread', 'old' => null, 'new' => 60],
		], $diff->differs);
		$this->assertSame(2, $diff->unchanged);
		$this->assertSame(['x'], $diff->boards_not_in_source);
		$this->assertFalse($diff->is_empty());
	}

	public function test_diff_after_import_is_empty(): void
	{
		$source = ConfigImporter::read(self::fixture('legacy_config_k8s.php'));
		$diff = ConfigImporter::diff(self::rows($source->values), $source);

		$this->assertTrue($diff->is_empty());
		$this->assertSame(array_sum(array_map('count', $source->values)), $diff->unchanged);
	}

	public function test_diff_new_board_needs_every_board_key(): void
	{
		$source = new ConfigSource(['c' => ['name' => 'New']], []);

		$this->expectException(ConfigImportException::class);
		$this->expectExceptionMessage('new board /c/ has no desc, type, position');
		ConfigImporter::diff([], $source);
	}

	public function test_apply_writes_differs_only_with_overwrite(): void
	{
		$diff = ConfigImporter::diff(self::rows(['global' => ['site_name' => 'Old']]), new ConfigSource(['global' => ['site_name' => 'New', 'delay' => 5]], []));

		$repository = $this->createMock(ConfigRepository::class);
		$repository->expects($this->once())->method('upsert')->with('global', 'delay', 5);
		$this->assertSame(1, ConfigImporter::apply($repository, $diff, false));

		$repository = $this->createMock(ConfigRepository::class);
		$repository->expects($this->exactly(2))->method('upsert');
		$this->assertSame(2, ConfigImporter::apply($repository, $diff, true));
	}

	public function test_format_value_masks_secrets(): void
	{
		$this->assertSame('<secret>', ConfigImporter::format_value('tripcode_salt', 'salt'));
		$this->assertSame('<secret>', ConfigImporter::format_value('hashid_salt', '_J9..salt'));
		$this->assertSame('null', ConfigImporter::format_value('hashid_salt', null));
		$this->assertSame('"Site"', ConfigImporter::format_value('site_name', 'Site'));
	}
}
