<?php

putenv('MB_TIMEZONE=UTC');
date_default_timezone_set('UTC');

if (!defined('__ROOT__')) {
	define('__ROOT__', dirname(__DIR__) . '/src');
}

if (!defined('__PUBLIC__')) {
	define('__PUBLIC__', sys_get_temp_dir() . '/miniboard_test_public');
}

if (!getenv('MB_DB_HOST')) putenv('MB_DB_HOST=localhost');
if (!getenv('MB_DB_NAME')) putenv('MB_DB_NAME=test');
if (!getenv('MB_DB_USER')) putenv('MB_DB_USER=test');
if (!getenv('MB_DB_PASS')) putenv('MB_DB_PASS=test');
if (!getenv('CSAM_SCANNER_HOST')) putenv('CSAM_SCANNER_HOST=localhost');
