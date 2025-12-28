<?php

namespace minichan\core;

use Closure;

require_once __ROOT__ . '/common/config.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/module.php';
require_once __DIR__ . '/plugin.php';

class App
{
	/**
	 * Singleton instance
	 * @var App
	 */
	private static App $instance;

	/**
	 * Summary of router
	 * @var Router
	 */
	private Router $router;

	/**
	 * Summary of hooks
	 * @var Closure[]
	 */
	private array $hooks;

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

	private final function __construct() {}

	public final function init(): void
	{
		$this->router = new Router();
		$this->hooks = [];
		$this->modules = [];
		foreach (MB_MODULES as $module_name)
		{
			$module = require __ROOT__ . "/modules/{$module_name}/module.php";
			$module->register_routes($this);
			$this->modules[] = $module;
		}
		$this->plugins = [];
		foreach (MB_PLUGINS as $plugin_name)
		{
			$plugin = require __ROOT__ . "/plugins/{$plugin_name}/plugin.php";
			$plugin->register_hooks($this);
			$this->plugins[] = $plugin;
		}
	}

	public static function get_instance(): App
	{
		if (!isset(self::$instance)) {
			self::$instance = new App;
			self::$instance->init();
		}

		return self::$instance;
	}

	public function process_request(string $req_method, string $req_uri)
	{
		$this->router->match_route($req_method, $req_uri);
	}

	public function add_hook(string $name, Closure $hook): void
	{
		if (!isset($this->hooks[$name])) {
			$this->hooks[$name] = [];
		}

		$this->hooks[$name][] = $hook;
	}

	public function run_hooks(string $name): void
	{
		if (!isset($this->hooks[$name])) return;
		foreach ($this->hooks[$name] as $hook) {
			call_user_func($hook);
		}
	}

	public function get_router(): Router
	{
		return $this->router;
	}

	public function get_hooks(): array
	{
		return $this->hooks;
	}

	public function get_modules(): array
	{
		return $this->modules;
	}

	public function get_plugins(): array
	{
		return $this->plugins;
	}
}
