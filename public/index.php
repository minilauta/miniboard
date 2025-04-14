<?php

namespace minichan;

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__);

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
$app->process_request($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
