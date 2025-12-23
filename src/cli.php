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
require __ROOT__ . '/core/db_connection.php';
require __ROOT__ . '/core/migrator.php';
require __ROOT__ . '/core/cleaner.php';

function print_help(): void {
	printf("cli: available commands: [migrate]\n");
}

if ($argc < 2) {
	printf("cli: invalid argument count\n");
	print_help();
	exit(1);
}

switch ($argv[1]) {
	case 'migrate': {
		$connection = new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
		$migrator = new core\Migrator($connection);
		$migrator->init();
		$migrator->migrate();
	} break;
	case 'cleanup': {
		$connection = new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
		$cleaner = new core\Cleaner($connection);
		$cleaner->clean_posts();
		$cleaner->clean_files();
	} break;
	default: {
		printf("cli: invalid command\n");
		print_help();
		exit(1);
	} break;
}
