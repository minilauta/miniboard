<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ConfigAccess;
use minichan\core\ConfigSchema;
use minichan\core\ConfigScope;
use minichan\core\ConfigStore;

require_once __ROOT__ . '/core/config_access.php';

class ConfigAccessTest extends TestCase
{
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

	/**
	 * Returns a store of a public board /b/, an admin board /a/, a superadmin board /s/ and an ignored board /x/ (no board keys).
	 */
	private function store(): ConfigStore
	{
		$board = fn(string $name, int $position) => ['name' => $name, 'desc' => '', 'type' => 'normal', 'position' => $position];

		return new ConfigStore($this->rows([
			'global' => ['site_name' => 'Site'],
			'b' => $board('Random', 10),
			'a' => $board('Admins', 20) + ['req_role' => MB_ROLE_ADMIN],
			's' => $board('Superadmins', 30) + ['req_role' => MB_ROLE_SUPERADMIN],
			'x' => ['delay' => 5],
		]));
	}

	private function access(array $req_roles): ConfigAccess
	{
		$rows = [];
		foreach ($req_roles as $key => $req_role) {
			$rows[] = ['key' => $key, 'req_role' => $req_role, 'timestamp' => 0];
		}

		return new ConfigAccess($rows);
	}

	public function test_defaults_to_superadmin(): void
	{
		$access = $this->access([]);

		$this->assertSame(MB_ROLE_SUPERADMIN, $access->req_role('threads_per_page'));
		$this->assertTrue($access->can_access(MB_ROLE_SUPERADMIN, 'threads_per_page'));
		$this->assertFalse($access->can_access(MB_ROLE_ADMIN, 'threads_per_page'));
		$this->assertFalse($access->can_access(MB_ROLE_MODERATOR, 'threads_per_page'));
		$this->assertSame([], $access->req_roles());
	}

	public function test_superadmin_can_access_every_key(): void
	{
		$access = $this->access([]);

		foreach (ConfigSchema::keys() as $key => $definition) {
			$this->assertTrue($access->can_access(MB_ROLE_SUPERADMIN, $key), $key);
		}
	}

	public function test_delegated_key_is_accessible_by_the_role_and_more_privileged_roles(): void
	{
		$access = $this->access(['threads_per_page' => MB_ROLE_MODERATOR, 'delay' => MB_ROLE_ADMIN]);

		$this->assertTrue($access->can_access(MB_ROLE_MODERATOR, 'threads_per_page'));
		$this->assertTrue($access->can_access(MB_ROLE_ADMIN, 'threads_per_page'));
		$this->assertTrue($access->can_access(MB_ROLE_SUPERADMIN, 'threads_per_page'));

		$this->assertFalse($access->can_access(MB_ROLE_MODERATOR, 'delay'));
		$this->assertTrue($access->can_access(MB_ROLE_ADMIN, 'delay'));

		$this->assertSame(['threads_per_page' => MB_ROLE_MODERATOR, 'delay' => MB_ROLE_ADMIN], $access->req_roles());
	}

	public function test_non_staff_roles_cannot_access(): void
	{
		$access = $this->access(['threads_per_page' => MB_ROLE_MODERATOR]);
		$store = $this->store();

		foreach ([null, 0, MB_ROLE_DISABLED, MB_ROLE_SYSTEM] as $role) {
			$this->assertFalse($access->can_access($role, 'threads_per_page'));
			$this->assertFalse(ConfigAccess::can_access_board($role, 'global', $store));
			$this->assertFalse($access->can_edit($role, 'global', 'threads_per_page', $store));
			$this->assertSame([], $access->keys_for($role, 'global', $store));
			$this->assertFalse($access->has_any($role, ConfigScope::Global, ConfigScope::Inheritable));
		}
	}

	public function test_unknown_key_cannot_be_accessed(): void
	{
		$this->assertFalse($this->access([])->can_access(MB_ROLE_SUPERADMIN, 'unknown_key'));
	}

	public function test_invalid_rows_are_ignored(): void
	{
		$access = new ConfigAccess([
			['key' => 'unknown_key', 'req_role' => MB_ROLE_ADMIN],
			['key' => 'delay', 'req_role' => 0],
			['key' => 'delay_thread', 'req_role' => 4],
			['key' => 'maxkb', 'req_role' => MB_ROLE_DISABLED],
			['key' => 'nsfw', 'req_role' => 'x'],
			['key' => 'flags', 'req_role' => MB_ROLE_SUPERADMIN],     // valid, the default
			['key' => 'text', 'req_role' => '3'],                     // valid, as returned by PDO without native types
		]);

		$this->assertCount(5, $access->errors());
		$this->assertSame(['text' => MB_ROLE_MODERATOR], $access->req_roles());
		$this->assertFalse($access->can_access(MB_ROLE_ADMIN, 'delay'));
	}

	public function test_every_key_can_be_delegated(): void
	{
		$rows = [];
		foreach (array_keys(ConfigSchema::keys()) as $key) {
			$rows[] = ['key' => $key, 'req_role' => MB_ROLE_MODERATOR];
		}

		$access = new ConfigAccess($rows);
		$this->assertSame([], $access->errors());

		// including secrets and keys rendered as HTML
		foreach (ConfigSchema::keys() as $key => $definition) {
			$this->assertNull(ConfigAccess::validate($key, MB_ROLE_MODERATOR), $key);
			$this->assertTrue($access->can_access(MB_ROLE_MODERATOR, $key), $key);
		}
	}

	public function test_validate(): void
	{
		$this->assertNull(ConfigAccess::validate('threads_per_page', MB_ROLE_MODERATOR));
		$this->assertNull(ConfigAccess::validate('threads_per_page', MB_ROLE_SUPERADMIN));

		$this->assertNotNull(ConfigAccess::validate('unknown_key', MB_ROLE_SUPERADMIN));
		$this->assertNotNull(ConfigAccess::validate('threads_per_page', 0));
		$this->assertNotNull(ConfigAccess::validate('threads_per_page', 4));
		$this->assertNotNull(ConfigAccess::validate('threads_per_page', MB_ROLE_DISABLED));
	}

	public function test_board_access_follows_the_board_req_role(): void
	{
		$store = $this->store();

		foreach (['global', 'b', 'a', 's', 'x', 'unknown'] as $board_id) {
			$this->assertTrue(ConfigAccess::can_access_board(MB_ROLE_SUPERADMIN, $board_id, $store), $board_id);
		}

		$this->assertTrue(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 'global', $store));
		$this->assertTrue(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 'b', $store));
		$this->assertTrue(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 'a', $store));
		$this->assertFalse(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 's', $store));
		$this->assertFalse(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 'x', $store));
		$this->assertFalse(ConfigAccess::can_access_board(MB_ROLE_ADMIN, 'unknown', $store));

		$this->assertTrue(ConfigAccess::can_access_board(MB_ROLE_MODERATOR, 'b', $store));
		$this->assertFalse(ConfigAccess::can_access_board(MB_ROLE_MODERATOR, 'a', $store));
	}

	public function test_can_edit(): void
	{
		$store = $this->store();
		$access = $this->access(['threads_per_page' => MB_ROLE_MODERATOR, 'position' => MB_ROLE_ADMIN, 'session_lifetime' => MB_ROLE_ADMIN]);

		// inheritable: site-wide and on the boards the role can access
		$this->assertTrue($access->can_edit(MB_ROLE_MODERATOR, 'global', 'threads_per_page', $store));
		$this->assertTrue($access->can_edit(MB_ROLE_MODERATOR, 'b', 'threads_per_page', $store));
		$this->assertFalse($access->can_edit(MB_ROLE_MODERATOR, 'a', 'threads_per_page', $store));
		$this->assertTrue($access->can_edit(MB_ROLE_ADMIN, 'a', 'threads_per_page', $store));
		$this->assertFalse($access->can_edit(MB_ROLE_ADMIN, 's', 'threads_per_page', $store));
		$this->assertTrue($access->can_edit(MB_ROLE_SUPERADMIN, 's', 'threads_per_page', $store));

		// board: on boards only
		$this->assertTrue($access->can_edit(MB_ROLE_ADMIN, 'b', 'position', $store));
		$this->assertFalse($access->can_edit(MB_ROLE_ADMIN, 'global', 'position', $store));
		$this->assertFalse($access->can_edit(MB_ROLE_MODERATOR, 'b', 'position', $store));

		// global: site-wide only
		$this->assertTrue($access->can_edit(MB_ROLE_ADMIN, 'global', 'session_lifetime', $store));
		$this->assertFalse($access->can_edit(MB_ROLE_ADMIN, 'b', 'session_lifetime', $store));

		// not delegated
		$this->assertFalse($access->can_edit(MB_ROLE_ADMIN, 'global', 'delay', $store));
		$this->assertTrue($access->can_edit(MB_ROLE_SUPERADMIN, 'global', 'delay', $store));

		$this->assertFalse($access->can_edit(MB_ROLE_SUPERADMIN, 'global', 'unknown_key', $store));
	}

	public function test_keys_for(): void
	{
		$store = $this->store();
		$access = $this->access(['threads_per_page' => MB_ROLE_MODERATOR, 'position' => MB_ROLE_ADMIN, 'session_lifetime' => MB_ROLE_ADMIN]);

		$this->assertSame(['session_lifetime', 'threads_per_page'], array_keys($access->keys_for(MB_ROLE_ADMIN, 'global', $store)));
		$this->assertSame(['position', 'threads_per_page'], array_keys($access->keys_for(MB_ROLE_ADMIN, 'b', $store)));
		$this->assertSame(['threads_per_page'], array_keys($access->keys_for(MB_ROLE_MODERATOR, 'global', $store)));
		$this->assertSame([], $access->keys_for(MB_ROLE_ADMIN, 's', $store));

		// a superadmin sees every key of the page, in schema order
		$this->assertSame(
			array_keys(ConfigSchema::keys_of(ConfigScope::Global, ConfigScope::Inheritable)),
			array_keys($access->keys_for(MB_ROLE_SUPERADMIN, 'global', $store)),
		);
		$this->assertSame(
			array_keys(ConfigSchema::keys_of(ConfigScope::Board, ConfigScope::Inheritable)),
			array_keys($access->keys_for(MB_ROLE_SUPERADMIN, 'x', $store)),
		);
	}

	public function test_has_any(): void
	{
		$access = $this->access(['position' => MB_ROLE_ADMIN]);

		$this->assertTrue($access->has_any(MB_ROLE_SUPERADMIN, ConfigScope::Global));
		$this->assertTrue($access->has_any(MB_ROLE_ADMIN, ConfigScope::Board, ConfigScope::Inheritable));
		$this->assertFalse($access->has_any(MB_ROLE_ADMIN, ConfigScope::Global, ConfigScope::Inheritable));
		$this->assertFalse($access->has_any(MB_ROLE_MODERATOR, ConfigScope::Board, ConfigScope::Inheritable));
	}
}
