<?php

namespace minichan;

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__);
define('__VENDOR__', __DIR__ . '/../vendor');

require __VENDOR__ . '/autoload.php';
require __ROOT__ . '/common/version.php';
require __ROOT__ . '/core/app.php';

try {
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
} catch (\Exception $ex) {
	require_once __ROOT__ . '/common/config.php';
	require_once __ROOT__ . '/core/renderer.php';
	$renderer = new core\HtmlRenderer();
	$err_code = $ex->getCode();
	$err_msg = $ex->getMessage();
	$vars = [
		'error_type' => $err_code,
		'error_message' => $err_msg,
	];
	if (array_key_exists($err_code, MB_ERROR_IMAGES)) {
		$vars['error_image'] = '/static/err_' . $err_code . '/' . MB_ERROR_IMAGES[$err_code][array_rand(MB_ERROR_IMAGES[$err_code])];
	}
	echo $renderer->render(__ROOT__ . '/common/templates/error.phtml', $vars);
}
