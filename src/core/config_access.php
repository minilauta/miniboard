<?php

namespace minichan\core;

require_once __DIR__ . '/../common/constants.php';
require_once __DIR__ . '/config_schema.php';
require_once __DIR__ . '/config_store.php';

/**
 * Reads and writes rows of the `config_access` table.
 */
class ConfigAccessRepository
{
	public function __construct(private \PDO $pdo) {}

	/**
	 * Returns every row of the config_access table.
	 *
	 * @return array<int, array{key: string, req_role: int, timestamp: int}>
	 */
	public function select_all(): array
	{
		$sth = $this->pdo->query('SELECT `key`, `req_role`, `timestamp` FROM config_access ORDER BY `key`');
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Sets the role required to view and edit a key, a superadmin (the default) deletes the row.
	 *
	 * @throws \InvalidArgumentException if the key or the role is invalid
	 */
	public function set(string $key, int $req_role, ?int $timestamp = null): void
	{
		$error = ConfigAccess::validate($key, $req_role);
		if ($error !== null) {
			throw new \InvalidArgumentException($error);
		}

		if ($req_role === MB_ROLE_SUPERADMIN) {
			$sth = $this->pdo->prepare('DELETE FROM config_access WHERE `key` = :key');
			$sth->execute(['key' => $key]);
			return;
		}

		$sth = $this->pdo->prepare('
			INSERT INTO config_access (`key`, `req_role`, `timestamp`)
			VALUES (:key, :req_role, :timestamp)
			ON DUPLICATE KEY UPDATE
				`req_role` = VALUES(`req_role`),
				`timestamp` = VALUES(`timestamp`)
		');
		$sth->execute([
			'key' => $key,
			'req_role' => $req_role,
			'timestamp' => $timestamp ?? time(),
		]);
	}
}

/**
 * The roles required to view and edit config keys in /manage/, the rows of the `config_access` table.
 *
 * A key without a row requires a superadmin. A superadmin can delegate any key to an admin or a moderator, a role can
 * then view and edit the key site-wide and on every board it can access, like the board req_role (lower is more
 * privileged).
 *
 * NOTE: delegating a key can hand over more than its value, e.g. a key rendered as HTML lets the role run scripts in
 * the browser of a superadmin, it is up to the superadmin.
 *
 * Invalid rows (unknown key, invalid role) are ignored, see errors().
 */
class ConfigAccess
{
	/** roles a key can require, the staff roles */
	public const ROLES = [MB_ROLE_SUPERADMIN, MB_ROLE_ADMIN, MB_ROLE_MODERATOR];

	/** @var array<string, int> key => req_role, the valid rows */
	private array $req_roles = [];

	/** @var string[] */
	private array $errors = [];

	/**
	 * @param array<int, array{key: string, req_role: int|string}> $rows rows of the config_access table
	 */
	public function __construct(array $rows)
	{
		foreach ($rows as $row) {
			$this->add_row($row);
		}
	}

	/**
	 * Loads the config_access table.
	 */
	public static function load(\PDO $pdo): self
	{
		return new self((new ConfigAccessRepository($pdo))->select_all());
	}

	/**
	 * Validates the role a key would require, returns an error message or null if the key and the role are valid.
	 */
	public static function validate(string $key, int $req_role): ?string
	{
		$definition = ConfigSchema::get($key);
		if ($definition === null) {
			return "unknown config key '{$key}'";
		}

		if (!in_array($req_role, self::ROLES, true)) {
			return "invalid role {$req_role}, expected a staff role (1 = superadmin, 2 = admin, 3 = moderator)";
		}

		return null;
	}

	/**
	 * Returns the role required to view and edit a key.
	 */
	public function req_role(string $key): int
	{
		return $this->req_roles[$key] ?? MB_ROLE_SUPERADMIN;
	}

	/**
	 * Returns the delegated keys and their roles (the keys that do not require a superadmin), key => req_role.
	 *
	 * @return array<string, int>
	 */
	public function req_roles(): array
	{
		return $this->req_roles;
	}

	/**
	 * Returns the problems of the rows, the rows were ignored.
	 *
	 * @return string[]
	 */
	public function errors(): array
	{
		return $this->errors;
	}

	/**
	 * Returns true if the role can view and edit the key, a superadmin can access every key.
	 *
	 * @param ?int $role role of the account, null if not logged in
	 */
	public function can_access(?int $role, string $key): bool
	{
		if (!self::is_staff($role) || ConfigSchema::get($key) === null) {
			return false;
		}

		return $role <= $this->req_role($key);
	}

	/**
	 * Returns true if the role can access a board in the config ('global' = site-wide, every staff role).
	 *
	 * A superadmin can access every board, including boards ignored for missing board keys (to fix or delete them),
	 * whether the board exists is up to the caller. Other roles can access the boards they can view (the board req_role).
	 */
	public static function can_access_board(?int $role, string $board_id, ConfigStore $store): bool
	{
		if (!self::is_staff($role)) {
			return false;
		}

		if ($role === MB_ROLE_SUPERADMIN || $board_id === ConfigSchema::GLOBAL) {
			return true;
		}

		$board = $store->board($board_id);
		return $board !== null && ($board['req_role'] === null || $role <= $board['req_role']);
	}

	/**
	 * Returns true if the role can view and edit the key on a board ('global' = site-wide).
	 */
	public function can_edit(?int $role, string $board_id, string $key, ConfigStore $store): bool
	{
		$definition = ConfigSchema::get($key);
		if ($definition === null || !$definition->allows_board_id($board_id)) {
			return false;
		}

		return $this->can_access($role, $key) && self::can_access_board($role, $board_id, $store);
	}

	/**
	 * Returns the keys of the config page of a board the role can view and edit, site-wide: the global and inheritable
	 * keys, a board: the board and inheritable keys.
	 *
	 * @param string $board_id 'global' or a board ID
	 * @return array<string, ConfigKey>
	 */
	public function keys_for(?int $role, string $board_id, ConfigStore $store): array
	{
		if (!self::can_access_board($role, $board_id, $store)) {
			return [];
		}

		$keys = $board_id === ConfigSchema::GLOBAL
			? ConfigSchema::keys_of(ConfigScope::Global, ConfigScope::Inheritable)
			: ConfigSchema::keys_of(ConfigScope::Board, ConfigScope::Inheritable);

		return array_filter($keys, fn(ConfigKey $definition) => $this->can_access($role, $definition->key));
	}

	/**
	 * Returns true if the role can access at least one key of the given scopes.
	 */
	public function has_any(?int $role, ConfigScope ...$scopes): bool
	{
		foreach (ConfigSchema::keys_of(...$scopes) as $key => $definition) {
			if ($this->can_access($role, $key)) {
				return true;
			}
		}

		return false;
	}

	private static function is_staff(?int $role): bool
	{
		return in_array($role, self::ROLES, true);
	}

	private function add_row(array $row): void
	{
		$key = (string) $row['key'];
		$req_role = filter_var($row['req_role'], FILTER_VALIDATE_INT);
		if ($req_role === false) {
			$this->errors[] = "ignored access of '{$key}', invalid role";
			return;
		}

		$error = self::validate($key, $req_role);
		if ($error !== null) {
			$this->errors[] = "ignored access of '{$key}', {$error}";
			return;
		}

		if ($req_role !== MB_ROLE_SUPERADMIN) {
			$this->req_roles[$key] = $req_role;
		}
	}
}
