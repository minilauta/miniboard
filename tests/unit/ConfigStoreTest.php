<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ConfigSchema;
use minichan\core\ConfigStore;

require_once __ROOT__ . '/core/config_store.php';

class ConfigStoreTest extends TestCase
{
	protected function tearDown(): void
	{
		ConfigStore::set_instance(null);
	}

	/**
	 * Returns config table rows of board_id => key => value.
	 */
	private function rows(array $values): array
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

	private function board(string $name, int $position, string $type = 'normal'): array
	{
		return ['name' => $name, 'desc' => '', 'type' => $type, 'position' => $position];
	}

	public function test_global_lookup(): void
	{
		$store = new ConfigStore($this->rows(['global' => ['site_name' => 'Site']]));

		$this->assertSame('Site', $store->get('site_name'));
		$this->assertSame('Site', $store->get('site_name', 'global'));
		$this->assertSame(ConfigSchema::get('site_desc')->default, $store->get('site_desc'));
		$this->assertSame('global', $store->source('site_name'));
		$this->assertSame('default', $store->source('site_desc'));
	}

	public function test_global_key_cannot_be_read_per_board(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		(new ConfigStore([]))->get('site_name', 'b');
	}

	public function test_unknown_key_throws(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		(new ConfigStore([]))->get('unknown_key');
	}

	public function test_inheritable_lookup_order(): void
	{
		$store = new ConfigStore($this->rows([
			'global' => ['delay' => 30, 'threads_per_page' => 20],
			'a' => $this->board('Anime', 10) + ['delay' => 5],
			'b' => $this->board('Random', 20),
		]));

		// board row, then the 'global' row, then the default
		$this->assertSame(5, $store->get('delay', 'a'));
		$this->assertSame(30, $store->get('delay', 'b'));
		$this->assertSame(30, $store->get('delay'));
		$this->assertSame(ConfigSchema::get('delay_thread')->default, $store->get('delay_thread', 'a'));

		$this->assertSame(5, $store->board('a')['delay']);
		$this->assertSame(30, $store->board('b')['delay']);
		$this->assertSame(20, $store->board('a')['threads_per_page']);
		$this->assertSame(ConfigSchema::get('maxkb')->default, $store->board('a')['maxkb']);

		$this->assertSame('board', $store->source('delay', 'a'));
		$this->assertSame('global', $store->source('delay', 'b'));
		$this->assertSame('default', $store->source('delay_thread', 'b'));
		$this->assertSame(['a'], $store->overrides('delay'));
	}

	public function test_null_overrides_the_site_wide_value(): void
	{
		$store = new ConfigStore($this->rows([
			'global' => ['req_role' => MB_ROLE_MODERATOR],
			'b' => $this->board('Random', 10) + ['req_role' => null],
			'mod' => $this->board('Moderator', 20),
		]));

		$this->assertNull($store->board('b')['req_role']);
		$this->assertSame(MB_ROLE_MODERATOR, $store->board('mod')['req_role']);
	}

	public function test_boards_are_resolved_and_ordered_by_position(): void
	{
		$store = new ConfigStore($this->rows([
			'b' => $this->board('Random', 20),
			'main' => $this->board('Main', 10, 'main'),
			'a' => $this->board('Anime', 20),
		]));

		$this->assertSame(['main', 'a', 'b'], array_keys($store->boards()));

		$board = $store->board('b');
		$this->assertSame('b', $board['id']);
		$this->assertSame('Random', $board['name']);
		foreach (array_keys(ConfigSchema::keys()) as $key) {
			if (ConfigSchema::get($key)->scope !== minichan\core\ConfigScope::Global) {
				$this->assertArrayHasKey($key, $board);
			}
		}

		$this->assertNull($store->board('global'));
		$this->assertNull($store->board('x'));
	}

	public function test_numeric_board_id_is_a_string(): void
	{
		$store = new ConfigStore($this->rows(['123' => $this->board('Numbers', 10)]));

		$this->assertSame('123', $store->board('123')['id']);
		$this->assertSame(['123'], $store->board_ids());
	}

	public function test_board_without_every_board_key_is_ignored(): void
	{
		$board = $this->board('Random', 10);
		unset($board['type']);
		$store = new ConfigStore($this->rows(['b' => $board, 'a' => $this->board('Anime', 20)]));

		$this->assertNull($store->board('b'));
		$this->assertSame(['a'], array_keys($store->boards()));
		$this->assertSame(['b', 'a'], $store->board_ids());
		$this->assertStringContainsString('ignored board /b/, it has no type', implode("\n", $store->errors()));
	}

	public function test_invalid_rows_are_ignored(): void
	{
		$rows = $this->rows(['global' => ['delay' => 30], 'b' => $this->board('Random', 10)]);
		$rows[] = ['board_id' => 'b', 'key' => 'site_name', 'type' => 'string', 'value' => 'board site name']; // global key on a board
		$rows[] = ['board_id' => 'global', 'key' => 'name', 'type' => 'string', 'value' => 'global name']; // board key site-wide
		$rows[] = ['board_id' => 'b', 'key' => 'delay', 'type' => 'string', 'value' => '5']; // wrong type
		$rows[] = ['board_id' => 'b', 'key' => 'delay_thread', 'type' => 'int', 'value' => 'abc']; // invalid value
		$rows[] = ['board_id' => 'b', 'key' => 'maxkb', 'type' => 'int', 'value' => '-1']; // fails validation
		$rows[] = ['board_id' => 'b', 'key' => 'removed_key', 'type' => 'int', 'value' => '1']; // unknown key
		$rows[] = ['board_id' => 'B', 'key' => 'name', 'type' => 'string', 'value' => 'x']; // invalid board ID
		$rows[] = ['board_id' => 'b', 'key' => 'tripcode_salt', 'type' => 'bool', 'value' => 'secret']; // secret, wrong type
		$store = new ConfigStore($rows);

		$this->assertCount(8, $store->errors());
		$this->assertSame(ConfigSchema::get('site_name')->default, $store->get('site_name'));
		$this->assertSame(30, $store->board('b')['delay']);
		$this->assertSame(ConfigSchema::get('delay_thread')->default, $store->board('b')['delay_thread']);
		$this->assertSame(ConfigSchema::get('maxkb')->default, $store->board('b')['maxkb']);
		$this->assertStringNotContainsString('secret', implode("\n", $store->errors()));
	}

	public function test_cfg_falls_back_to_defaults(): void
	{
		ConfigStore::set_instance(null);
		$this->assertSame(ConfigSchema::get('error_images')->default, cfg('error_images'));
		$this->assertSame([], ConfigStore::instance()->boards());

		ConfigStore::set_instance(new ConfigStore($this->rows(['global' => ['site_name' => 'Site']])));
		$this->assertSame('Site', cfg('site_name'));
	}

	public function test_boot_loads_the_instance(): void
	{
		$pdo = $this->create_pdo($this->rows(['global' => ['site_name' => 'Booted'], 'b' => $this->board('Random', 10)]));
		ConfigStore::boot($pdo);

		$this->assertSame('Booted', cfg('site_name'));
		$this->assertSame(['b'], array_keys(ConfigStore::instance()->boards()));
	}

	public function test_boot_empty_config_throws_but_keeps_the_defaults(): void
	{
		try {
			ConfigStore::boot($this->create_pdo([]));
			$this->fail('boot must throw');
		} catch (\AppException $e) {
			$this->assertSame(SC_SERVICE_UNAVAILABLE, $e->getCode());
			$this->assertStringContainsString('config:import', $e->getMessage());
		}

		$this->assertSame(ConfigSchema::get('styles')->default, cfg('styles'));
	}

	private function create_pdo(array $rows): \PDO
	{
		$sth = $this->createMock(\PDOStatement::class);
		$sth->method('fetchAll')->willReturn($rows);

		$pdo = $this->createMock(\PDO::class);
		$pdo->method('query')->willReturn($sth);

		return $pdo;
	}
}
