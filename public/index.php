<?php

namespace minichan;

define('__ROOT__', __DIR__ . '/../src');
define('__PUBLIC__', __DIR__);
define('__VENDOR__', __DIR__ . '/../vendor');

require __VENDOR__ . '/autoload.php';
require __ROOT__ . '/common/version.php';
require __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/constants.php';
require_once __ROOT__ . '/common/database/db_handle.php';
require_once __ROOT__ . '/core/config_store.php';
require __ROOT__ . '/core/translator.php';
require __ROOT__ . '/core/app.php';
require __ROOT__ . '/middlewares/session_mw.php';

$translator = new core\Translator(__ROOT__ . '/lang', 'en');

if (defined('MB_BOARDS')) {
	error_log('config: legacy src/common/config.php detected, its settings are ignored');
}

$app = null;
try {
	core\ConfigStore::boot(get_db_handle());
	$app = new core\App(MB_MODULES, cfg('plugins'), [
		middlewares\session_mw(cfg('session_lifetime'), intdiv(cfg('session_lifetime'), 2)),
	]);
	$app->process_request($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
} catch (\Exception $ex) {
	require_once __ROOT__ . '/core/renderer.php';
	$renderer = new core\HtmlRenderer(['app' => $app]);
	$err_code = $ex->getCode();
	$err_msg = $ex->getMessage();
	$vars = [
		'error_type' => $err_code,
		'error_message' => $err_msg,
	];
	$error_images = cfg('error_images');
	if (array_key_exists($err_code, $error_images)) {
		$vars['error_image'] = '/static/err_' . $err_code . '/' . $error_images[$err_code][array_rand($error_images[$err_code])];
	}
	echo $renderer->render(__ROOT__ . '/common/templates/error.phtml', $vars);
}
