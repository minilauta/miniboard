<?php

namespace minichan\core;

require_once __DIR__ . '/config_store.php';

/**
 * A config source could not be read or imported, see $errors.
 */
class ConfigImportException extends \RuntimeException
{
	/**
	 * @param string[] $errors
	 */
	public function __construct(public readonly array $errors)
	{
		parent::__construct(implode("\n", $errors));
	}
}

/**
 * The validated values of a config source (a legacy config.php or an export).
 */
final class ConfigSource
{
	/**
	 * @param array<string, array<string, mixed>> $values board_id ('global' or a board ID) => key => value
	 * @param string[] $warnings
	 */
	public function __construct(
		public readonly array $values,
		public readonly array $warnings,
	) {}
}

/**
 * Differences between a config source and the rows of the config table.
 */
final class ConfigDiff
{
	/** @var array<int, array{board_id: string, key: string, value: mixed}> rows of the source missing from the table */
	public array $inserts = [];

	/** @var array<int, array{board_id: string, key: string, old: mixed, new: mixed}> rows with a different value, old is null for an invalid row */
	public array $differs = [];

	/** @var int rows with the same value */
	public int $unchanged = 0;

	/** @var string[] boards of the table missing from the source, they are left as is */
	public array $boards_not_in_source = [];

	public function is_empty(): bool
	{
		return count($this->inserts) === 0 && count($this->differs) === 0;
	}
}

/**
 * Imports config from a legacy src/common/config.php or from an export (see to_json()), and exports config.
 *
 * Legacy configs are executed in a separate PHP process, their constants would collide with the constants of the
 * current process. Only run this from the CLI, never on a file uploaded through the web UI: it executes PHP.
 */
class ConfigImporter
{
	/** legacy constants that are not settings */
	private const LEGACY_IGNORED = [
		'MB_ENV', 'MB_DB_HOST', 'MB_DB_NAME', 'MB_DB_USER', 'MB_DB_PASS', 'FILE_SCANNER_HOST', 'MB_VERSION',
		'MB_MODULES', 'MB_IMPORT_TABLE_TYPES', 'MB_BOARDS',
		'MB_TIMEFRAME', 'MB_TIMEFRAME_REPLY_LIMIT', // never read by the code
	];
	private const LEGACY_IGNORED_PREFIXES = ['MB_ROLE_', 'MB_IMPORT_TINYIB_'];

	/** gap between the positions of imported boards, leaves room for new boards in between */
	private const POSITION_STEP = 10;

	/**
	 * Reads a legacy config.php (.php) or an export (.json).
	 *
	 * @param bool $hoist legacy only: write the board keys that have the same value on every board once, site-wide
	 * @throws ConfigImportException
	 */
	public static function read(string $path, bool $hoist = true): ConfigSource
	{
		if (!is_file($path) || !is_readable($path)) {
			throw new ConfigImportException(["cannot read '{$path}'"]);
		}

		switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
			case 'php':
				$constants = self::read_legacy_constants($path, $output);
				$source = self::from_legacy_constants($constants, $hoist);
				if ($output === '') {
					return $source;
				}
				return new ConfigSource($source->values, array_merge(["executing '{$path}' printed: {$output}"], $source->warnings));
			case 'json':
				return self::from_json(file_get_contents($path));
			default:
				throw new ConfigImportException(["unsupported file '{$path}', expected a legacy config .php or an export .json"]);
		}
	}

	/**
	 * Returns the defaults of the schema as site-wide values, for a new install. Boards are not defaults, a new install
	 * has no boards until they are created (e.g. in /manage/).
	 */
	public static function defaults(): ConfigSource
	{
		$values = [];
		foreach (ConfigSchema::keys_of(ConfigScope::Global, ConfigScope::Inheritable) as $key => $definition) {
			$values[$key] = $definition->default;
		}

		return new ConfigSource([ConfigSchema::GLOBAL => $values], []);
	}

	/**
	 * Executes a legacy config.php in a separate PHP process and returns the constants it defines.
	 *
	 * @param ?string $output set to what the file printed (e.g. PHP warnings)
	 * @return array<string, mixed>
	 * @throws ConfigImportException
	 */
	public static function read_legacy_constants(string $path, ?string &$output = null): array
	{
		$code = 'require $argv[1]; echo json_encode(get_defined_constants(true)["user"] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);';
		$command = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-d', 'error_reporting=-1', '-r', $code, '--', $path];

		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if (!is_resource($process)) {
			throw new ConfigImportException(["cannot execute '{$path}'"]);
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = trim(stream_get_contents($pipes[2]));
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit_code = proc_close($process);

		if ($exit_code !== 0) {
			throw new ConfigImportException(["executing '{$path}' failed with exit code {$exit_code}" . ($stderr !== '' ? ": {$stderr}" : '')]);
		}

		try {
			$constants = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new ConfigImportException(["cannot read the constants of '{$path}': {$e->getMessage()}"]);
		}

		if (!is_array($constants)) {
			throw new ConfigImportException(["cannot read the constants of '{$path}'"]);
		}

		$output = $stderr;
		return $constants;
	}

	/**
	 * Converts the constants of a legacy config.php into config values.
	 *
	 * The site-wide constants (MB_SITE_NAME, ...) become 'global' values, the MB_BOARDS entries become board values and
	 * the position of a board is its order in MB_BOARDS. Missing keys get their default, with a warning.
	 *
	 * @param array<string, mixed> $constants
	 * @param bool $hoist write the inheritable board keys that have the same value on every board once, site-wide
	 * @throws ConfigImportException
	 */
	public static function from_legacy_constants(array $constants, bool $hoist = true): ConfigSource
	{
		$errors = [];
		$warnings = [];
		$values = [ConfigSchema::GLOBAL => []];

		// site-wide constants
		$mapped = [];
		$board_keys = [];
		foreach (ConfigSchema::keys() as $key => $definition) {
			if ($definition->legacy === 'MB_BOARDS') {
				$board_keys[$key] = $definition;
				continue;
			}

			if ($definition->legacy === null) {
				continue;
			}

			$mapped[$definition->legacy] = true;
			if (array_key_exists($definition->legacy, $constants)) {
				$values[ConfigSchema::GLOBAL][$key] = $constants[$definition->legacy];
			} else {
				$values[ConfigSchema::GLOBAL][$key] = $definition->default;
				$warnings[] = "{$definition->legacy} is not set, {$key} is set to its default";
			}
		}

		foreach (array_keys($constants) as $name) {
			if (isset($mapped[$name]) || in_array($name, self::LEGACY_IGNORED, true)) {
				continue;
			}

			foreach (self::LEGACY_IGNORED_PREFIXES as $prefix) {
				if (str_starts_with($name, $prefix)) {
					continue 2;
				}
			}

			$warnings[] = "{$name} is not a setting, it was ignored";
		}

		if (isset($constants['MB_MODULES']) && defined('MB_MODULES') && $constants['MB_MODULES'] !== MB_MODULES) {
			$warnings[] = 'MB_MODULES differs from the modules of src/common/constants.php, the modules are set there now';
		}

		// boards
		$boards = $constants['MB_BOARDS'] ?? null;
		if (!is_array($boards) || count($boards) === 0) {
			$errors[] = 'MB_BOARDS is not set or has no boards';
			$boards = [];
		}

		$position = 0;
		foreach ($boards as $board_id => $board) {
			$board_id = (string) $board_id;
			$where = "MB_BOARDS['{$board_id}']";
			$position += self::POSITION_STEP;

			if (($error = ConfigSchema::validate_board_id($board_id)) !== null) {
				$errors[] = "{$where}: {$error}";
				continue;
			}

			if (!is_array($board)) {
				$errors[] = "{$where} is not an array";
				continue;
			}

			if (isset($board['id']) && (string) $board['id'] !== $board_id) {
				$errors[] = "{$where} has a different id '{$board['id']}'";
			}

			$values[$board_id] = [];
			foreach ($board_keys as $key => $definition) {
				if (array_key_exists($key, $board)) {
					$values[$board_id][$key] = $board[$key];
				} else {
					$values[$board_id][$key] = $definition->default;
					$warnings[] = "{$where}['{$key}'] is not set, it is set to its default";
				}
			}
			$values[$board_id]['position'] = $position;

			foreach (array_keys($board) as $key) {
				if ($key !== 'id' && !isset($board_keys[$key])) {
					$warnings[] = "{$where}['{$key}'] is not a setting, it was ignored";
				}
			}
		}

		$errors = array_merge($errors, self::validate_values($values));
		if (count($errors) > 0) {
			throw new ConfigImportException($errors);
		}

		return new ConfigSource(self::normalize($hoist ? self::hoist($values) : $values), $warnings);
	}

	/**
	 * Reads an export, see to_json(). An export can be partial, e.g. a few edited keys.
	 *
	 * @throws ConfigImportException
	 */
	public static function from_json(string $json): ConfigSource
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new ConfigImportException(["invalid JSON: {$e->getMessage()}"]);
		}

		if (!is_array($data)) {
			throw new ConfigImportException(['invalid export, expected an object of board IDs']);
		}

		$errors = [];
		$values = [];
		foreach ($data as $board_id => $keys) {
			$board_id = (string) $board_id;
			if (!is_array($keys)) {
				$errors[] = "'{$board_id}' is not an object of keys";
				continue;
			}

			foreach ($keys as $key => $entry) {
				$where = "'{$key}' of '{$board_id}'";
				$definition = ConfigSchema::get((string) $key);
				if ($definition === null) {
					$errors[] = "{$where}: unknown config key";
					continue;
				}

				if (!is_array($entry) || !array_key_exists('type', $entry) || !array_key_exists('value', $entry)) {
					$errors[] = "{$where}: expected an object with 'type' and 'value'";
					continue;
				}

				if ($entry['type'] !== $definition->type->value) {
					$errors[] = "{$where}: type is " . json_encode($entry['type']) . " but the key is {$definition->type->value}";
					continue;
				}

				$values[$board_id][$key] = $entry['value'];
			}
		}

		$errors = array_merge($errors, self::validate_values($values));
		if (count($errors) > 0) {
			throw new ConfigImportException($errors);
		}

		return new ConfigSource(self::normalize($values), []);
	}

	/**
	 * Moves the inheritable keys that have the same value on every board to 'global', the resolved values do not change.
	 *
	 * @param array<string, array<string, mixed>> $values
	 * @return array<string, array<string, mixed>>
	 */
	public static function hoist(array $values): array
	{
		$values[ConfigSchema::GLOBAL] ??= [];
		$board_ids = array_values(array_filter(array_map('strval', array_keys($values)), fn(string $id) => $id !== ConfigSchema::GLOBAL));
		if (count($board_ids) === 0) {
			return $values;
		}

		foreach (array_keys(ConfigSchema::keys_of(ConfigScope::Inheritable)) as $key) {
			$first_board_id = $board_ids[0];
			if (!array_key_exists($key, $values[$first_board_id])) {
				continue;
			}

			$value = $values[$first_board_id][$key];
			foreach ($board_ids as $board_id) {
				if (!array_key_exists($key, $values[$board_id]) || $values[$board_id][$key] !== $value) {
					continue 2;
				}
			}

			if (array_key_exists($key, $values[ConfigSchema::GLOBAL]) && $values[ConfigSchema::GLOBAL][$key] !== $value) {
				continue;
			}

			$values[ConfigSchema::GLOBAL][$key] = $value;
			foreach ($board_ids as $board_id) {
				unset($values[$board_id][$key]);
			}
		}

		return $values;
	}

	/**
	 * Compares a source with the rows of the config table.
	 *
	 * @param array<int, array{board_id: string, key: string, type: string, value: ?string}> $rows
	 * @throws ConfigImportException if a new board of the source does not have every board key
	 */
	public static function diff(array $rows, ConfigSource $source): ConfigDiff
	{
		$store = new ConfigStore($rows);
		$current = $store->values();

		$exists = [];
		foreach ($rows as $row) {
			$exists[(string) $row['board_id']][(string) $row['key']] = true;
		}

		$errors = [];
		$board_keys = array_keys(ConfigSchema::keys_of(ConfigScope::Board));
		foreach ($source->values as $board_id => $keys) {
			$board_id = (string) $board_id;
			if ($board_id === ConfigSchema::GLOBAL || isset($exists[$board_id])) {
				continue;
			}

			$missing = array_diff($board_keys, array_keys($keys));
			if (count($missing) > 0) {
				$errors[] = "new board /{$board_id}/ has no " . implode(', ', $missing);
			}
		}

		if (count($errors) > 0) {
			throw new ConfigImportException($errors);
		}

		$diff = new ConfigDiff();
		foreach ($source->values as $board_id => $keys) {
			$board_id = (string) $board_id;
			foreach ($keys as $key => $value) {
				if (!isset($exists[$board_id][$key])) {
					$diff->inserts[] = ['board_id' => $board_id, 'key' => $key, 'value' => $value];
				} else if (!$store->has($board_id, $key) || $current[$board_id][$key] !== $value) {
					$diff->differs[] = ['board_id' => $board_id, 'key' => $key, 'old' => $current[$board_id][$key] ?? null, 'new' => $value];
				} else {
					$diff->unchanged++;
				}
			}
		}

		foreach (array_keys($exists) as $board_id) {
			$board_id = (string) $board_id;
			if ($board_id !== ConfigSchema::GLOBAL && !isset($source->values[$board_id])) {
				$diff->boards_not_in_source[] = $board_id;
			}
		}

		return $diff;
	}

	/**
	 * Writes the rows of a diff, the differing rows only if $overwrite is true. Never deletes rows.
	 *
	 * @return int written rows
	 */
	public static function apply(ConfigRepository $repository, ConfigDiff $diff, bool $overwrite): int
	{
		$written = 0;
		$timestamp = time();

		foreach ($diff->inserts as $insert) {
			$repository->upsert($insert['board_id'], $insert['key'], $insert['value'], $timestamp);
			$written++;
		}

		if ($overwrite) {
			foreach ($diff->differs as $differ) {
				$repository->upsert($differ['board_id'], $differ['key'], $differ['new'], $timestamp);
				$written++;
			}
		}

		return $written;
	}

	/**
	 * Exports values as JSON: {"<board_id>": {"<key>": {"type": "<type>", "value": <value>}}}, 'global' first, then the
	 * boards by position. Secrets are included.
	 *
	 * @param array<string, array<string, mixed>> $values board_id => key => value, e.g. ConfigStore::values()
	 */
	public static function to_json(array $values): string
	{
		$board_ids = array_map('strval', array_keys($values));
		usort($board_ids, function (string $a, string $b) use ($values) {
			if ($a === ConfigSchema::GLOBAL || $b === ConfigSchema::GLOBAL) {
				return ($b === ConfigSchema::GLOBAL) <=> ($a === ConfigSchema::GLOBAL);
			}

			return [$values[$a]['position'] ?? PHP_INT_MAX, $a] <=> [$values[$b]['position'] ?? PHP_INT_MAX, $b];
		});

		$export = new \stdClass();
		foreach ($board_ids as $board_id) {
			$keys = new \stdClass();
			foreach (ConfigSchema::keys() as $key => $definition) {
				if (array_key_exists($key, $values[$board_id])) {
					$keys->$key = ['type' => $definition->type->value, 'value' => $values[$board_id][$key]];
				}
			}
			$export->$board_id = $keys;
		}

		return json_encode($export, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n";
	}

	/**
	 * Returns a printable value, secrets are masked.
	 */
	public static function format_value(string $key, mixed $value): string
	{
		if ($value !== null && ConfigSchema::get($key)?->secret) {
			return '<secret>';
		}

		$text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		return strlen($text) > 120 ? substr($text, 0, 117) . '...' : $text;
	}

	/**
	 * Orders the keys of every board in schema order, the same values always have the same order.
	 *
	 * @param array<string, array<string, mixed>> $values
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize(array $values): array
	{
		$order = array_flip(array_keys(ConfigSchema::keys()));
		foreach ($values as &$keys) {
			uksort($keys, fn(string $a, string $b) => $order[$a] <=> $order[$b]);
		}

		return $values;
	}

	/**
	 * @param array<string, array<string, mixed>> $values
	 * @return string[]
	 */
	private static function validate_values(array $values): array
	{
		$errors = [];
		foreach ($values as $board_id => $keys) {
			foreach ($keys as $key => $value) {
				$error = ConfigSchema::validate_write((string) $board_id, (string) $key, $value);
				if ($error !== null) {
					$errors[] = ((string) $board_id === ConfigSchema::GLOBAL ? '' : "/{$board_id}/: ") . $error;
				}
			}
		}

		return $errors;
	}
}
