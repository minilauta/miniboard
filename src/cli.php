<?php

namespace minichan;

if (php_sapi_name() !== 'cli') {
	exit(1);
}

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__ . '/../public');
define('__VENDOR__', __DIR__ . '/../vendor');

require __VENDOR__ . '/autoload.php';
require __ROOT__ . '/common/version.php';
require __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/constants.php';
require __ROOT__ . '/core/db_connection.php';
require __ROOT__ . '/core/migrator.php';
require __ROOT__ . '/core/cleaner.php';
require __ROOT__ . '/core/analyzer.php';
require_once __ROOT__ . '/core/config_importer.php';

use minichan\core\ConfigDiff;
use minichan\core\ConfigImporter;
use minichan\core\ConfigImportException;
use minichan\core\ConfigRepository;
use minichan\core\ConfigSource;
use minichan\core\ConfigStore;

function print_help(): void {
	printf("cli: available commands: [migrate, clean:posts, clean:files, analyze:files, config:import, config:export]\n");
	printf("cli: config:import <file.php|file.json> [--dry-run] [--overwrite] [--no-hoist]\n");
	printf("       imports a legacy config.php or an export, by default only the keys missing from the database are written\n");
	printf("       --dry-run    print the changes without writing them, 0 changes after an import means the import is exact\n");
	printf("       --overwrite  also write the keys that have a different value in the database (boards are never deleted)\n");
	printf("       --no-hoist   legacy only: write every board key per board, instead of once site-wide for keys that have the same value on every board\n");
	printf("cli: config:export [file.json]\n");
	printf("       exports the config (including secrets) to a file or stdout\n");
}

function new_connection(): core\DbConnection {
	return new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
}

function config_print_source_warnings(ConfigSource $source): void {
	foreach ($source->warnings as $warning) {
		printf("config: warning: %s\n", $warning);
	}
}

function config_print_import_errors(ConfigImportException $e): void {
	fprintf(STDERR, "config: import failed:\n");
	foreach ($e->errors as $error) {
		fprintf(STDERR, "config:   %s\n", $error);
	}
}

function config_print_diff(ConfigDiff $diff, bool $overwrite): void {
	$where = fn(array $row) => $row['board_id'] === core\ConfigSchema::GLOBAL ? "global.{$row['key']}" : "/{$row['board_id']}/.{$row['key']}";

	foreach ($diff->inserts as $row) {
		printf("config: insert   %s = %s\n", $where($row), ConfigImporter::format_value($row['key'], $row['value']));
	}

	foreach ($diff->differs as $row) {
		printf(
			"config: %s %s = %s (database: %s)\n",
			$overwrite ? 'update  ' : 'differs ',
			$where($row),
			ConfigImporter::format_value($row['key'], $row['new']),
			ConfigImporter::format_value($row['key'], $row['old'])
		);
	}

	foreach ($diff->boards_not_in_source as $board_id) {
		printf("config: board /%s/ is in the database but not in the source, it was left as is\n", $board_id);
	}

	printf(
		"config: %d to insert, %d %s, %d unchanged%s\n",
		count($diff->inserts),
		count($diff->differs),
		$overwrite ? 'to update' : 'differing (kept, pass --overwrite to update)',
		$diff->unchanged,
		$diff->is_empty() ? ' - no changes' : ''
	);
}

/**
 * Imports a source, see print_help().
 */
function config_import(core\DbConnection $connection, ConfigSource $source, bool $dry_run, bool $overwrite): void {
	config_print_source_warnings($source);

	$connection->transaction(function (\PDO $pdo) use ($source, $dry_run, $overwrite) {
		$repository = new ConfigRepository($pdo);
		$diff = ConfigImporter::diff($repository->select_all(), $source);
		config_print_diff($diff, $overwrite);

		if ($dry_run) {
			printf("config: dry run, nothing was written\n");
			return;
		}

		$written = ConfigImporter::apply($repository, $diff, $overwrite);
		printf("config: wrote %d rows\n", $written);
	});
}

/**
 * Exports the config to a file, or to stdout if no file is given, see print_help().
 */
function config_export(core\DbConnection $connection, ?string $path): void {
	$store = null;
	$connection->transaction(function (\PDO $pdo) use (&$store) {
		$store = ConfigStore::load($pdo);
	});

	foreach ($store->errors() as $error) {
		fprintf(STDERR, "config: warning: %s\n", $error);
	}

	$json = ConfigImporter::to_json($store->values());
	if ($path === null) {
		echo $json;
		return;
	}

	if (file_put_contents($path, $json) === false) {
		fprintf(STDERR, "config: cannot write '%s'\n", $path);
		exit(1);
	}

	printf("config: exported to %s\n", $path);
}

/**
 * Seeds the config table after the migrations if it is empty, from the first source found:
 * 1. the file of the MB_LEGACY_CONFIG env variable
 * 2. src/common/config.php if it is a legacy config (e.g. a still mounted k8s ConfigMap)
 * 3. the site-wide defaults of the schema (no boards), only for a new install (no posts) or MB_ENV=dev
 */
function config_seed(core\DbConnection $connection): void {
	$is_empty = false;
	$has_posts = false;
	$connection->transaction(function (\PDO $pdo) use (&$is_empty, &$has_posts) {
		$is_empty = (new ConfigRepository($pdo))->count() === 0;
		$has_posts = $pdo->query('SELECT 1 FROM posts LIMIT 1')->fetchColumn() !== false;
	});

	if (!$is_empty) {
		printf("config: already initialized, skipping seed\n");
		return;
	}

	$legacy_env = getenv('MB_LEGACY_CONFIG');
	if ($legacy_env !== false && $legacy_env !== '') {
		printf("config: seeding from MB_LEGACY_CONFIG: %s\n", $legacy_env);
		$source = ConfigImporter::read($legacy_env);
	} else if (defined('MB_BOARDS')) {
		printf("config: seeding from the legacy src/common/config.php\n");
		$source = ConfigImporter::read(__ROOT__ . '/common/config.php');
	} else if (!$has_posts || MB_ENV === 'dev') {
		printf("config: seeding the site-wide defaults, there are no boards: create them in /manage/?route=boards or run config:import <file>\n");
		$source = ConfigImporter::defaults();
	} else {
		fprintf(STDERR, "config: the config table is empty and the database has posts, refusing to seed the defaults\n");
		fprintf(STDERR, "config: set MB_LEGACY_CONFIG to the legacy config.php and run migrate again, or run config:import <file>\n");
		exit(1);
	}

	config_import($connection, $source, false, false);
}

if ($argc < 2) {
	printf("cli: invalid argument count\n");
	print_help();
	exit(1);
}

try {
	switch ($argv[1]) {
		case 'migrate': {
			$connection = new_connection();
			$migrator = new core\Migrator($connection);
			$migrator->init();
			$migrator->migrate();
			config_seed($connection);
		} break;
		case 'clean:posts': {
			$connection = new_connection();
			$cleaner = new core\Cleaner($connection);
			$cleaner->clean_posts();
		} break;
		case 'clean:files': {
			$connection = new_connection();
			$cleaner = new core\Cleaner($connection);
			$cleaner->clean_files();
		} break;
		case 'analyze:files': {
			$connection = new_connection();
			$analyzer = new core\Analyzer($connection);
			$analyzer->analyze_files();
		} break;
		case 'config:import': {
			$args = array_slice($argv, 2);
			$flags = array_values(array_filter($args, fn(string $arg) => str_starts_with($arg, '--')));
			$files = array_values(array_filter($args, fn(string $arg) => !str_starts_with($arg, '--')));
			$unknown = array_diff($flags, ['--dry-run', '--overwrite', '--no-hoist']);
			if (count($files) !== 1 || count($unknown) > 0) {
				printf("cli: invalid arguments for config:import\n");
				print_help();
				exit(1);
			}

			$source = ConfigImporter::read($files[0], !in_array('--no-hoist', $flags, true));
			config_import(new_connection(), $source, in_array('--dry-run', $flags, true), in_array('--overwrite', $flags, true));
		} break;
		case 'config:export': {
			config_export(new_connection(), $argv[2] ?? null);
		} break;
		default: {
			printf("cli: invalid command\n");
			print_help();
			exit(1);
		} break;
	}
} catch (ConfigImportException $e) {
	config_print_import_errors($e);
	exit(1);
}
