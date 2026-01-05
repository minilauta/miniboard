<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/core/cache.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/database.php';

class HomeModule implements core\Module
{
	private core\HtmlRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
	}

	public function __destruct() {}

	public function init(core\App &$app): void
	{
		$this->renderer->set_var('app', $app);
	}

	public function register_middleware(core\Router &$router, Closure $handler): void {}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/', function ($vars) {
			echo $this->renderer->render(__DIR__ . '/templates/root.phtml', [
				'site_name' => MB_SITE_NAME,
				'site_desc' => MB_SITE_DESC,
				'site_stats' => select_site_stats(),
			]);
		});
	}

	public function get_name(): string
	{
		return 'home';
	}

	public function get_index(): string
	{
		return '/';
	}
}

return new HomeModule();
