<?php

namespace minichan;

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__);
define('__VENDOR__', __DIR__ . '/../vendor');

require __VENDOR__ . '/autoload.php';
require __ROOT__ . '/common/version.php';
require __ROOT__ . '/core/app.php';

$app = new core\App(['home', 'board'], []);
$app->get_router()->add_middleware(function () {
	session_set_cookie_params([
		'lifetime' => 315360000,
		'path' => '/',
		'domain' => '',
		'secure' => false,
		'httponly' => false,
		'samesite' => 'Lax'
	]);
	session_start();
});
$app->process_request($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
