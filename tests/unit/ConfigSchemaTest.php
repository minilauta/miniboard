<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ConfigImporter;
use minichan\core\ConfigSchema;
use minichan\core\ConfigScope;

require_once __ROOT__ . '/core/config_importer.php';

class ConfigSchemaTest extends TestCase
{
	public function test_keys_are_valid(): void
	{
		foreach (ConfigSchema::keys() as $key => $definition) {
			$this->assertSame($key, $definition->key);
			$this->assertMatchesRegularExpression('/^[a-z0-9_]{1,64}$/', $key);

			// board keys are required on every board, their defaults are placeholders and never used as values
			if ($definition->scope !== ConfigScope::Board) {
				$this->assertNull($definition->validate($definition->default), "default of {$key} is invalid");
			}
		}
	}

	public function test_defaults_are_the_legacy_repo_config(): void
	{
		$constants = ConfigImporter::read_legacy_constants(dirname(__DIR__) . '/fixtures/legacy_config_repo.php');

		foreach (ConfigSchema::keys() as $key => $definition) {
			if ($definition->legacy === null || $definition->legacy === 'MB_BOARDS') {
				continue;
			}

			$this->assertArrayHasKey($definition->legacy, $constants);
			$this->assertSame($constants[$definition->legacy], $definition->default, "default of {$key}");
		}

		// board defaults are the /a/ board
		foreach (ConfigSchema::keys_of(ConfigScope::Inheritable) as $key => $definition) {
			if ($definition->legacy === 'MB_BOARDS' && $key !== 'hashid_salt') {
				$this->assertSame($constants['MB_BOARDS']['a'][$key], $definition->default, "default of {$key}");
			}
		}
	}

	public function test_scopes(): void
	{
		$this->assertNull(ConfigSchema::validate_write('global', 'site_name', 'x'));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'site_name', 'x'));

		$this->assertNull(ConfigSchema::validate_write('global', 'captcha_thread', false));
		$this->assertNull(ConfigSchema::validate_write('b', 'captcha_thread', false));

		$this->assertNull(ConfigSchema::validate_write('b', 'name', 'Random'));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'name', 'Random'));
	}

	public function test_write_validates_type_and_value(): void
	{
		$this->assertNotNull(ConfigSchema::validate_write('global', 'unknown_key', 1));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'delay', '15'));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'delay', -1));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'delay', null));
		$this->assertNull(ConfigSchema::validate_write('b', 'req_role', null));
		$this->assertNull(ConfigSchema::validate_write('b', 'req_role', MB_ROLE_MODERATOR));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'req_role', MB_ROLE_DISABLED));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'type', 'other'));
	}

	public function test_board_ids(): void
	{
		$this->assertNull(ConfigSchema::validate_board_id('b'));
		$this->assertNull(ConfigSchema::validate_board_id('text'));
		$this->assertNull(ConfigSchema::validate_board_id('abcd1234'));

		foreach (['global', 'manage', 'logs', 'bans', 'css', 'static', '', 'B', 'abcdefghi', '../x', 'a b'] as $board_id) {
			$this->assertNotNull(ConfigSchema::validate_board_id($board_id), "board ID '{$board_id}' must be invalid");
			$this->assertNotNull(ConfigSchema::validate_write($board_id, 'name', 'x'), "board ID '{$board_id}' must be invalid");
		}
	}

	public function test_plugins_must_exist(): void
	{
		$this->assertNull(ConfigSchema::validate_write('global', 'plugins', []));
		$this->assertNull(ConfigSchema::validate_write('global', 'plugins', ['friends', 'winter2025']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'plugins', ['nonexistent']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'plugins', ['../modules/manage']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'plugins', ['friends/../../x']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'plugins', ['name' => 'friends']));
	}

	public function test_names_and_file_names_are_validated(): void
	{
		$this->assertNotNull(ConfigSchema::validate_write('global', 'styles', []));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'styles', ['../evil']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'styles', ['futaba" onload="x']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'error_images', [404 => ['../../etc/passwd']]));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'error_images', [404 => []]));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'error_images', ['x' => ['a.png']]));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'banner_images', ['../b.png' => 'b']));
		$this->assertNotNull(ConfigSchema::validate_write('global', 'banner_images', ['b.png' => '"><script>']));
		$this->assertNull(ConfigSchema::validate_write('global', 'banner_images', []));
	}

	public function test_board_json_values_are_validated(): void
	{
		$this->assertNotNull(ConfigSchema::validate_write('b', 'fields_post', ['name' => ['type' => 'string']]));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'fields_post', ['name' => ['required' => false, 'type' => 'string', 'max_len' => '75']]));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'mime_ext_types', ['image/png' => []]));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'mime_ext_types', ['png' => ['png']]));
		$this->assertNotNull(ConfigSchema::validate_write('b', 'embed_types', ['youtube.com' => 'file:///etc/passwd']));
		$this->assertNull(ConfigSchema::validate_write('b', 'embed_types', []));
	}
}
