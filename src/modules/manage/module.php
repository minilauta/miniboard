<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';

class ManageModule implements core\Module
{
	private core\HtmlRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer(__DIR__ . '/templates');
	}

	public function __destruct()
	{

	}

	public function register_middleware(Closure $handler): void
	{
		
	}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/manage', function ($vars) {
			echo $this->renderer->render('manage.phtml');
		});
	}

	public function get_name(): string
	{
		return 'manage';
	}
}

return new ManageModule();
