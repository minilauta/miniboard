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
	private core\FileCache $cache;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
		$this->cache = new core\FileCache("module_home");
	}

	public function __destruct()
	{

	}

	public function register_middleware(Closure $handler): void
	{

	}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/', function ($vars) {
			$stats = $this->cache->get("stats");
			if ($stats == null) {
				$stats = $this->renderer->render(__DIR__ . '/templates/root.phtml', [
					'site_name' => MB_SITE_NAME,
					'site_desc' => MB_SITE_DESC,
					'site_stats' => select_site_stats()
				]);
				$this->cache->set("stats", $stats, 5);
			}

			echo $stats;
		});
	}

	public function get_name(): string
	{
		return 'home';
	}
}

return new HomeModule();
