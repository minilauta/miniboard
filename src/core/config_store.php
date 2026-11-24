<?php

namespace minichan\core {

	require_once __DIR__ . '/../common/exception.php';
	require_once __DIR__ . '/config_schema.php';

	/**
	 * Reads and writes rows of the `config` table.
	 */
	class ConfigRepository
	{
		public function __construct(private \PDO $pdo) {}

		/**
		 * Returns every row of the config table.
		 *
		 * @return array<int, array{board_id: string, key: string, type: string, value: ?string, timestamp: int}>
		 */
		public function select_all(): array
		{
			$sth = $this->pdo->query('SELECT `board_id`, `key`, `type`, `value`, `timestamp` FROM config ORDER BY `board_id`, `key`');
			return $sth->fetchAll(\PDO::FETCH_ASSOC);
		}

		public function count(): int
		{
			return (int) $this->pdo->query('SELECT COUNT(*) FROM config')->fetchColumn();
		}

		/**
		 * Inserts or updates a value, the value is validated against the schema.
		 *
		 * @param string $board_id 'global' or a board ID
		 * @throws \InvalidArgumentException if the value cannot be written
		 */
		public function upsert(string $board_id, string $key, mixed $value, ?int $timestamp = null): void
		{
			$error = ConfigSchema::validate_write($board_id, $key, $value);
			if ($error !== null) {
				throw new \InvalidArgumentException($error);
			}

			$type = ConfigSchema::get($key)->type;
			$sth = $this->pdo->prepare('
				INSERT INTO config (`board_id`, `key`, `type`, `value`, `timestamp`)
				VALUES (:board_id, :key, :type, :value, :timestamp)
				ON DUPLICATE KEY UPDATE
					`type` = VALUES(`type`),
					`value` = VALUES(`value`),
					`timestamp` = VALUES(`timestamp`)
			');
			$sth->execute([
				'board_id' => $board_id,
				'key' => $key,
				'type' => $type->value,
				'value' => $type->encode($value),
				'timestamp' => $timestamp ?? time(),
			]);
		}

		/**
		 * Deletes a value, an inheritable key of a board inherits the site-wide value again.
		 */
		public function delete(string $board_id, string $key): bool
		{
			$sth = $this->pdo->prepare('DELETE FROM config WHERE `board_id` = :board_id AND `key` = :key');
			$sth->execute(['board_id' => $board_id, 'key' => $key]);
			return $sth->rowCount() > 0;
		}

		/**
		 * Deletes every value of a board, i.e. the board.
		 */
		public function delete_board(string $board_id): int
		{
			if ($board_id === ConfigSchema::GLOBAL) {
				throw new \InvalidArgumentException('the site-wide config cannot be deleted');
			}

			$sth = $this->pdo->prepare('DELETE FROM config WHERE `board_id` = :board_id');
			$sth->execute(['board_id' => $board_id]);
			return $sth->rowCount();
		}
	}

	/**
	 * The config of the `config` table, resolved per board.
	 *
	 * A value is looked up in this order:
	 * - global keys: the 'global' row, then the default of the key
	 * - inheritable keys: the row of the board, then the 'global' row, then the default of the key
	 * - board keys: the row of the board, a board without every board key is ignored (and logged)
	 *
	 * Invalid rows (unknown key, wrong scope, type or value) are ignored, see errors().
	 */
	class ConfigStore
	{
		private static ?ConfigStore $instance = null;

		/** @var array<string, array<string, mixed>> board_id => key => value, the valid rows as stored */
		private array $values = [];

		/** @var array<string, array<string, mixed>> board_id => resolved config of the board, ordered by position */
		private array $boards = [];

		/** @var string[] */
		private array $errors = [];

		/**
		 * @param array<int, array{board_id: string, key: string, type: string, value: ?string}> $rows rows of the config table
		 */
		public function __construct(array $rows)
		{
			foreach ($rows as $row) {
				$this->add_row($row);
			}

			$this->resolve_boards();
		}

		/**
		 * Loads the config table.
		 */
		public static function load(\PDO $pdo): self
		{
			return new self((new ConfigRepository($pdo))->select_all());
		}

		/**
		 * Loads the config of the current request, read by cfg() and the funcs_common board config functions.
		 *
		 * @throws \AppException (503) if the config table is empty
		 */
		public static function boot(\PDO $pdo): self
		{
			$store = self::load($pdo);
			foreach ($store->errors as $error) {
				error_log("config: {$error}");
			}

			self::$instance = $store;

			if ($store->is_empty()) {
				throw new \AppException('config', 'boot', 'config is not initialized, run `php src/cli.php config:import <file>`', SC_SERVICE_UNAVAILABLE);
			}

			return $store;
		}

		/**
		 * Returns the config of the current request, or the defaults if it was not loaded (e.g. the database is unavailable).
		 */
		public static function instance(): self
		{
			return self::$instance ??= new self([]);
		}

		/**
		 * Replaces the config of the current request, null resets it to the defaults.
		 */
		public static function set_instance(?self $store): void
		{
			self::$instance = $store;
		}

		/**
		 * Returns the value of a key.
		 *
		 * @param ?string $board_id null for site-wide values, required for board keys, not allowed for global keys
		 * @throws \InvalidArgumentException if the key does not exist or cannot be read this way
		 */
		public function get(string $key, ?string $board_id = null): mixed
		{
			$definition = ConfigSchema::get($key);
			if ($definition === null) {
				throw new \InvalidArgumentException("unknown config key '{$key}'");
			}

			switch ($definition->scope) {
				case ConfigScope::Global:
					if ($board_id !== null && $board_id !== ConfigSchema::GLOBAL) {
						throw new \InvalidArgumentException("{$key} is site-wide, it cannot be read per board");
					}
					return $this->lookup(ConfigSchema::GLOBAL, $definition);
				case ConfigScope::Inheritable:
					if ($board_id !== null && $this->has($board_id, $key)) {
						return $this->values[$board_id][$key];
					}
					return $this->lookup(ConfigSchema::GLOBAL, $definition);
				case ConfigScope::Board:
					if ($board_id === null || $board_id === ConfigSchema::GLOBAL) {
						throw new \InvalidArgumentException("{$key} is a board key, a board ID is required");
					}
					$board = $this->board($board_id);
					if ($board === null) {
						throw new \InvalidArgumentException("board /{$board_id}/ does not exist");
					}
					return $board[$key];
			}
		}

		/**
		 * Returns the resolved config of a board (every board and inheritable key, and 'id'), null if it does not exist.
		 */
		public function board(string $board_id): ?array
		{
			return $board_id === ConfigSchema::GLOBAL ? null : ($this->boards[$board_id] ?? null);
		}

		/**
		 * Returns the resolved config of every board, keyed by board ID and ordered by position.
		 *
		 * @return array<string, array<string, mixed>>
		 */
		public function boards(): array
		{
			return $this->boards;
		}

		/**
		 * Returns the IDs of every board that has a row, including the boards ignored for missing board keys.
		 *
		 * @return string[]
		 */
		public function board_ids(): array
		{
			$board_ids = [];
			foreach (array_keys($this->values) as $board_id) {
				if ((string) $board_id !== ConfigSchema::GLOBAL) {
					$board_ids[] = (string) $board_id;
				}
			}

			return $board_ids;
		}

		/**
		 * Returns the valid rows as stored (not resolved), board_id => key => value.
		 *
		 * @return array<string, array<string, mixed>>
		 */
		public function values(): array
		{
			return $this->values;
		}

		/**
		 * Returns true if a valid row of the key exists on the board ('global' = site-wide).
		 */
		public function has(string $board_id, string $key): bool
		{
			return isset($this->values[$board_id]) && array_key_exists($key, $this->values[$board_id]);
		}

		/**
		 * Returns where the value of a key on a board comes from: 'board' (a row of the board), 'global' (the site-wide row)
		 * or 'default' (the default of the key).
		 */
		public function source(string $key, string $board_id = ConfigSchema::GLOBAL): string
		{
			if ($board_id !== ConfigSchema::GLOBAL && $this->has($board_id, $key)) {
				return 'board';
			}

			$definition = ConfigSchema::get($key);
			if ($definition !== null && $definition->scope !== ConfigScope::Board && $this->has(ConfigSchema::GLOBAL, $key)) {
				return ConfigSchema::GLOBAL;
			}

			return 'default';
		}

		/**
		 * Returns the IDs of the boards that override the site-wide value of an inheritable key.
		 *
		 * @return string[]
		 */
		public function overrides(string $key): array
		{
			return array_values(array_filter($this->board_ids(), fn(string $board_id) => $this->has($board_id, $key)));
		}

		/**
		 * Returns true if the config table has no valid rows.
		 */
		public function is_empty(): bool
		{
			return count($this->values) === 0;
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

		private function lookup(string $board_id, ConfigKey $definition): mixed
		{
			return $this->has($board_id, $definition->key) ? $this->values[$board_id][$definition->key] : $definition->default;
		}

		private function add_row(array $row): void
		{
			$board_id = (string) $row['board_id'];
			$key = (string) $row['key'];
			$where = $board_id === ConfigSchema::GLOBAL ? "'{$key}' (global)" : "'{$key}' of /{$board_id}/";

			$definition = ConfigSchema::get($key);
			if ($definition === null) {
				$this->errors[] = "ignored {$where}, unknown key";
				return;
			}

			if ($board_id !== ConfigSchema::GLOBAL && ($error = ConfigSchema::validate_board_id($board_id)) !== null) {
				$this->errors[] = "ignored {$where}, {$error}";
				return;
			}

			if (!$definition->allows_board_id($board_id)) {
				$this->errors[] = "ignored {$where}, the key is {$definition->scope->value}";
				return;
			}

			if ($row['type'] !== $definition->type->value) {
				$this->errors[] = "ignored {$where}, its type is {$row['type']} but the key is {$definition->type->value}";
				return;
			}

			try {
				$value = $definition->type->decode($row['value']);
			} catch (\UnexpectedValueException $e) {
				$this->errors[] = "ignored {$where}, " . ($definition->secret ? 'invalid value' : $e->getMessage());
				return;
			}

			$error = $definition->validate($value);
			if ($error !== null) {
				$this->errors[] = "ignored {$where}, {$error}";
				return;
			}

			$this->values[$board_id][$key] = $value;
		}

		private function resolve_boards(): void
		{
			$board_keys = ConfigSchema::keys_of(ConfigScope::Board);
			$resolved_keys = ConfigSchema::keys_of(ConfigScope::Board, ConfigScope::Inheritable);

			$boards = [];
			foreach ($this->board_ids() as $board_id) {
				$missing = array_keys(array_diff_key($board_keys, $this->values[$board_id]));
				if (count($missing) > 0) {
					$this->errors[] = "ignored board /{$board_id}/, it has no " . implode(', ', $missing);
					continue;
				}

				$board = ['id' => $board_id];
				foreach ($resolved_keys as $key => $definition) {
					$board[$key] = $this->has($board_id, $key) ? $this->values[$board_id][$key] : $this->lookup(ConfigSchema::GLOBAL, $definition);
				}
				$boards[$board_id] = $board;
			}

			uasort($boards, fn(array $a, array $b) => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);
			$this->boards = $boards;
		}
	}
}

namespace {
	/**
	 * Returns the value of a config key, see minichan\core\ConfigStore::get().
	 *
	 * Inside a board, read the resolved board config instead, e.g. $board_cfg['captcha_thread'].
	 */
	function cfg(string $key, ?string $board_id = null): mixed
	{
		return \minichan\core\ConfigStore::instance()->get($key, $board_id);
	}
}
