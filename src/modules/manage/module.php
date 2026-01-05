<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __DIR__ . '/funcs_manage.php';

class ManageModule implements core\Module
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

		$router->add_route(HTTP_GET, '/manage/logout', function ($vars) {
			if (!funcs_manage_logout()) {
				throw new \AppException('index', 'route', "logout failed to clear PHP session", SC_INTERNAL_ERROR);
			}

			header('Location: /');
			http_response_code(303);
		});

		$router->add_route(HTTP_POST, '/manage/login', function ($vars) {
			return handle_loginform($vars);
		});

		function handle_loginform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'username'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 75],
				'password'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 256]
			]);

			// validate captcha
			if (MB_CAPTCHA_LOGIN) {
				funcs_common_validate_captcha($_POST);
			}

			// get account
			$account = select_account($_POST['username']);
			if ($account == null) {
				throw new \AppException('index', 'route', "invalid username or password for user '{$_POST['username']}'", SC_UNAUTHORIZED);
			}

			// attempt to login
			$login = funcs_manage_login($account, $_POST['password']);
			if ($login !== true) {
				throw new \AppException('index', 'route', "invalid username or password for user '{$_POST['username']}'", SC_UNAUTHORIZED);
			}

			// update lastactive
			$account['lastactive'] = time();
			if (update_account($account) !== TRUE) {
				throw new \AppException('index', 'route', "failed to update account info for user {$_POST['username']}", SC_INTERNAL_ERROR);
			}

			header('Location: /manage/');
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/import', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_importform($vars);
		});

		function handle_importform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'db_name'    => ['required' => true, 'type' => 'string'],
				'db_user'    => ['required' => true, 'type' => 'string'],
				'db_pass'    => ['required' => true, 'type' => 'string'],
				'table_name' => ['required' => true, 'type' => 'string'],
				'table_type' => ['required' => true, 'type' => 'string'],
				'board_id'   => ['required' => true, 'type' => 'string']
			]);

			// execute import
			$status = funcs_manage_import($_POST);

			header("Location: /manage/?route=import&status={$status}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/rebuild', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_rebuildform($vars);
		});

		function handle_rebuildform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id'   => ['required' => true, 'type' => 'string']
			]);

			// execute rebuild
			$status = funcs_manage_rebuild($_POST);

			header("Location: /manage/?route=rebuild&status={$status}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/refresh', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_refreshform($vars);
		});

		function handle_refreshform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id'   => ['required' => true, 'type' => 'string']
			]);

			// execute refresh
			$status = funcs_manage_refresh($_POST);

			header("Location: /manage/?route=refresh&status={$status}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/delete', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_deleteform($vars);
		});

		function handle_manage_deleteform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array']
			]);

			// execute delete
			$status = funcs_manage_delete($_POST['select']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/ban', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_banform($vars);
		});

		function handle_manage_banform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array'],
				'duration' => ['required' => true, 'type' => 'string'],
				'reason'   => ['required' => true, 'type' => 'string']
			]);
			
			// parse request fields
			$_POST['duration'] = funcs_common_parse_input_int($_POST, 'duration', 60, 5, 60 * 24 * 365);

			// cleanup expired bans
			cleanup_bans();

			// execute ban
			$status = funcs_manage_ban($_POST['select'], $_POST['duration'] * 60, $_POST['reason']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/approve', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_approveform($vars);
		});

		function handle_manage_approveform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array']
			]);

			// execute approve
			$status = funcs_manage_approve($_POST['select']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/toggle_lock', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_togglelockform($vars);
		});

		function handle_manage_togglelockform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array']
			]);

			// execute toggle lock
			$status = funcs_manage_toggle_lock($_POST['select']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/toggle_sticky', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_togglestickyform($vars);
		});

		function handle_manage_togglestickyform($vars) {
			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array']
			]);

			// execute toggle sticky
			$status = funcs_manage_toggle_sticky($_POST['select']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/csam_scanner/cp', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array']
			]);

			// execute mark as cp
			$status = funcs_manage_csam_scanner_cp($_POST['select']);

			// execute ban
			$status .= "<br>";
			$status .= funcs_manage_ban($_POST['select'], 525960 * 60, 'CSAM-scanner: marked as CP');

			// execute delete
			$status .= "<br>";
			$status .= funcs_manage_delete($_POST['select']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		});
	}

	public function get_name(): string
	{
		return 'manage';
	}

	public function get_index(): string
	{
		return '/manage/';
	}
}

return new ManageModule();
