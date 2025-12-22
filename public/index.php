<?php

namespace minichan;

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__);
define('__VENDOR__', __DIR__ . '/../vendor');

require __VENDOR__ . '/autoload.php';
require __ROOT__ . '/common/version.php';
require __ROOT__ . '/core/app.php';

// TODO: fix this
// function app_error_handler(int $errno, string $errstr, ?string $errfile, ?int $errline, ?array $errcontext): bool {
// 	require_once __ROOT__ . '/core/renderer.php';
	
// 	$renderer = new core\HtmlRenderer();
// 	$vars = [
// 		'error_type' => $errno,
// 		'error_message' => $errstr,
// 	];
// 	if (array_key_exists($errno, MB_ERROR_IMAGES)) {
// 		$vars['error_image'] = '/static/err_' . $errno . '/' . MB_ERROR_IMAGES[$errno][array_rand(MB_ERROR_IMAGES[$errno])];
// 	}
// 	echo $renderer->render(__ROOT__ . '/common/templates/error.phtml');
	
// 	return true;
// }

// set_error_handler('app_error_handler', E_ALL);
// set_exception_handler('app_error_handler');

$app = new core\App(['home', 'manage', 'board'], []);
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
