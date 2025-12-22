<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __DIR__ . '/funcs_manage.php';

class ManageModule implements core\Module
{
	private core\HtmlRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
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
			if (!funcs_common_is_logged_in()) {
				echo $this->renderer->render(__DIR__ . '/templates/login.phtml');
				return;
			}
			
			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_route = funcs_common_parse_input_str($query_params, 'route', '');
			$query_status = funcs_common_parse_input_str($query_params, 'status', '');
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			echo $this->renderer->render(__DIR__ . '/templates/manage.phtml', [
				'route' => $query_route,
				'status' => $query_status,
				'page' => $query_page,
			]);
		});
	}

	public function get_name(): string
	{
		return 'manage';
	}
}

return new ManageModule();
