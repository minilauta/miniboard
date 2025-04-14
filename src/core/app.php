<?php

namespace minichan\core;

require_once __DIR__ . '/router.php';
require_once __DIR__ . '/module.php';
require_once __DIR__ . '/plugin.php';

class App
{
	/**
	 * Summary of router
	 * @var Router
	 */
	private Router $router;
	/**
	 * Summary of modules
	 * @var Module[]
	 */
	private array $modules;
	/**
	 * Summary of plugins
	 * @var Plugin[]
	 */
	private array $plugins;

	public function __construct(array $modules_enabled, array $plugins_enabled)
	{
		$this->router = new Router();

		foreach ($modules_enabled as $module_name)
		{
			$module = require __ROOT__ . "/modules/{$module_name}/module.php";
			$module->register_routes($this->router);
			$this->modules[] = $module;
		}

		$this->plugins = [];
	}

	public function process_request(string $req_method, string $req_uri)
	{
		$this->router->match_route($req_method, $req_uri);
		// echo "<pre>";
		// print_r($this->router->routes);
		// echo "</pre>";
	}

	public function get_router(): Router
	{
		return $this->router;
	}
}
