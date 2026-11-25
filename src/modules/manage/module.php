<?php

namespace minichan\modules;

use Closure;
use minichan\core;
use minichan\core\integrations;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/core/integrations/file_scanner.php';
require_once __ROOT__ . '/core/post_filter.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/database.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __DIR__ . '/funcs_manage.php';

class ManageModule implements core\Module
{
	private core\HtmlRenderer $renderer;
	private integrations\FileScannerClient $file_scanner;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
		$this->file_scanner = new integrations\FileScannerClient('http://' . FILE_SCANNER_HOST . ':8000');
	}

	public function __destruct() {}

	public function init(core\App &$app): void
	{
		$this->renderer->set_var('app', $app);
	}

	public function register_middleware(core\Router &$router): void
	{
		// re-validate logged in account on every request, the session role is used by all modules
		$router->add_middleware(function () {
			funcs_manage_refresh_session();
		});
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
			$query_route = htmlspecialchars(funcs_common_parse_input_str($query_params, 'route', ''), ENT_QUOTES, 'UTF-8');
			$query_status = htmlspecialchars(funcs_common_parse_input_str($query_params, 'status', ''), ENT_QUOTES, 'UTF-8');
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			echo $this->renderer->render(__DIR__ . '/templates/manage.phtml', [
				'query_params' => $query_params,
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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'username'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 75],
				'password'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 256]
			]);

			// validate captcha
			if (cfg('captcha_login')) {
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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

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

			header("Location: /manage/?route=import&status=" . urlencode($status));
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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id'   => ['required' => true, 'type' => 'string']
			]);

			// execute rebuild
			$status = funcs_manage_rebuild($_POST);

			header("Location: /manage/?route=rebuild&status=" . urlencode($status));
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/delete', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_deleteform($vars);
		});

		function handle_manage_deleteform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'   => ['required' => true, 'type' => 'array'],
				'duration' => ['required' => true, 'type' => 'string'],
				'reason'   => ['required' => true, 'type' => 'string']
			]);

			// parse request fields
			$_POST['duration'] = funcs_common_parse_input_int($_POST, 'duration', 60, 5, 60 * 24 * 365);
			$capture = isset($_POST['capture']);

			// optional subnet prefix (ban the whole CIDR block of the poster)
			$prefix = (isset($_POST['prefix']) && $_POST['prefix'] !== '') ? intval($_POST['prefix']) : null;

			// optional WHOIS network ban (ban the poster's allocated network range)
			$network = isset($_POST['network']);

			// cleanup expired bans
			funcs_common_cleanup_bans();

			// execute ban
			$status = funcs_manage_ban($_POST['select'], $_POST['duration'] * 60, $_POST['reason'], $capture, $prefix, $network);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/create_ban', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_createbanform($vars);
		});

		function handle_manage_createbanform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'ip'       => ['required' => true, 'type' => 'string'],
				'duration' => ['required' => true, 'type' => 'string'],
				'reason'   => ['required' => true, 'type' => 'string'],
				'comment'  => ['required' => false, 'type' => 'string']
			]);

			// parse request fields
			$duration = funcs_common_parse_input_int($_POST, 'duration', 60, 5, 60 * 24 * 365);

			// execute create ban
			$status = funcs_manage_create_ban($_POST['ip'], $duration * 60, $_POST['reason'], $_POST['comment'] ?? '');

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/edit_ban', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_editbanform($vars);
		});

		function handle_manage_editbanform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id'       => ['required' => true, 'type' => 'string'],
				'duration' => ['required' => true, 'type' => 'string'],
				'reason'   => ['required' => true, 'type' => 'string'],
				'comment'  => ['required' => false, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);
			$duration = funcs_common_parse_input_int($_POST, 'duration', 60, 5, 60 * 24 * 365);

			// execute edit ban
			$status = funcs_manage_edit_ban($id, $duration * 60, $_POST['reason'], $_POST['comment'] ?? '');

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/delete_ban', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_deletebanform($vars);
		});

		function handle_manage_deletebanform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id' => ['required' => true, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);

			// execute delete ban
			$status = funcs_manage_delete_ban($id);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/create_account', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_createaccountform($vars);
		});

		function handle_manage_createaccountform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'username'         => ['required' => true, 'type' => 'string', 'max_len' => 75],
				'role'             => ['required' => true, 'type' => 'string'],
				'password'         => ['required' => true, 'type' => 'string', 'max_len' => 72],
				'password_confirm' => ['required' => true, 'type' => 'string', 'max_len' => 72]
			]);

			// parse request fields
			$role = funcs_common_parse_input_int($_POST, 'role', null);

			// execute create account
			$status = funcs_manage_create_account($_POST['username'], $role, $_POST['password'], $_POST['password_confirm']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/edit_account', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_editaccountform($vars);
		});

		function handle_manage_editaccountform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id'               => ['required' => true, 'type' => 'string'],
				'role'             => ['required' => false, 'type' => 'string'],
				'password'         => ['required' => false, 'type' => 'string', 'max_len' => 72],
				'password_confirm' => ['required' => false, 'type' => 'string', 'max_len' => 72]
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);
			$role = isset($_POST['role']) ? intval($_POST['role']) : null;

			// execute edit account
			$status = funcs_manage_edit_account($id, $role, $_POST['password'] ?? '', $_POST['password_confirm'] ?? '');

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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

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
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

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

		$router->add_route(HTTP_POST, '/manage/move_thread', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] > MB_ROLE_ADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_movethreadform($vars);
		});

		function handle_manage_movethreadform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'        => ['required' => true, 'type' => 'array'],
				'dst_board_id'  => ['required' => true, 'type' => 'string']
			]);

			// parse selected thread (only one thread allowed)
			if (count($_POST['select']) !== 1) {
				throw new \AppException('index', 'route', 'select exactly one thread to move', SC_BAD_REQUEST);
			}

			$selected = explode('/', $_POST['select'][0]);
			$src_board_id = $selected[0];
			$thread_id = intval($selected[1]);
			$dst_board_id = $_POST['dst_board_id'];

			// execute move
			$status = funcs_manage_move_thread($src_board_id, $thread_id, $dst_board_id);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/edit_post', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_editpostform($vars);
		});

		function handle_manage_editpostform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id'  => ['required' => true, 'type' => 'string'],
				'post_id'   => ['required' => true, 'type' => 'string'],
				'name'      => ['required' => false, 'type' => 'string'],
				'email'     => ['required' => false, 'type' => 'string'],
				'subject'   => ['required' => false, 'type' => 'string'],
				'message'   => ['required' => false, 'type' => 'string'],
				'notice'    => ['required' => false, 'type' => 'string'],
			]);

			$post = [
				'board_id' => $_POST['board_id'],
				'post_id'  => funcs_common_parse_input_int($_POST, 'post_id', null, 0),
				'name'     => $_POST['name'] ?? '',
				'email'    => $_POST['email'] ?? '',
				'subject'  => $_POST['subject'] ?? '',
				'message'  => $_POST['message'] ?? '',
				'notice'   => $_POST['notice'] ?? '',
				'silent'   => isset($_POST['silent']),
				'delfile'  => isset($_POST['delfile']),
			];

			// execute edit
			$status = funcs_manage_edit_post($post);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/file_scanner/ban', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'select'       => ['required' => true, 'type' => 'array'],
				'content_type' => ['required' => false, 'type' => 'string', 'max_len' => 32],
				'comment'      => ['required' => false, 'type' => 'string']
			]);

			// the 'Mark as CSAM' shortcut always bans as CSAM, otherwise the content type is selected
			$csam = isset($_POST['csam']);
			$content_type = $csam
				? integrations\BannedContentType::Csam
				: integrations\BannedContentType::tryFrom($_POST['content_type'] ?? '');
			if ($content_type === null) {
				throw new \AppException('index', 'route', 'select a content type for the file ban', SC_BAD_REQUEST);
			}

			// optional free text stored with the hashes, validated before any posts are banned or deleted
			$comment = trim($_POST['comment'] ?? '');
			if (mb_strlen($comment) > 256) {
				throw new \AppException('index', 'route', 'file ban comment exceeds maximum length of 256 characters', SC_BAD_REQUEST);
			}

			// execute ban first, it needs the posts (CSAM posters are banned for a year)
			$status = '';
			if ($csam) {
				$status .= funcs_manage_ban($_POST['select'], 525960 * 60, 'Marked as CSAM');
				$status .= "<br>";
			}

			// execute file ban and delete, CSAM is deleted even if banning its files fails,
			// otherwise the posts are kept so the ban can be retried
			$status .= funcs_manage_file_scanner_ban($this->file_scanner, $_POST['select'], $content_type, $comment !== '' ? $comment : null, !$csam);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		});

		$router->add_route(HTTP_POST, '/manage/create_file_scanner_hash', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_createfilescannerhashform($vars);
		});

		function handle_manage_createfilescannerhashform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'sha256'       => ['required' => true, 'type' => 'string', 'max_len' => 128],
				'pdq'          => ['required' => false, 'type' => 'string', 'max_len' => 128],
				'quality'      => ['required' => false, 'type' => 'string'],
				'type'         => ['required' => true, 'type' => 'string', 'max_len' => 32],
				'content_type' => ['required' => true, 'type' => 'string', 'max_len' => 32],
				'originator'   => ['required' => false, 'type' => 'string'],
				'comment'      => ['required' => false, 'type' => 'string']
			]);

			// parse request fields
			$type = integrations\BannedMediaType::tryFrom($_POST['type']);
			if ($type === null) {
				throw new \AppException('index', 'route', 'select a media type for the file hash', SC_BAD_REQUEST);
			}

			$content_type = integrations\BannedContentType::tryFrom($_POST['content_type']);
			if ($content_type === null) {
				throw new \AppException('index', 'route', 'select a content type for the file hash', SC_BAD_REQUEST);
			}

			// PDQ quality defaults to the max when left empty
			$quality = (isset($_POST['quality']) && $_POST['quality'] !== '') ? funcs_common_parse_input_int($_POST, 'quality', 100, 0, 100) : 100;

			// execute create file scanner hash
			$status = funcs_manage_create_file_scanner_hash($_POST['sha256'], $_POST['pdq'] ?? '', $quality, $type, $content_type, $_POST['comment'] ?? '', $_POST['originator'] ?? '');

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/edit_file_scanner_hash', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_editfilescannerhashform($vars);
		});

		function handle_manage_editfilescannerhashform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id'           => ['required' => true, 'type' => 'string'],
				'content_type' => ['required' => true, 'type' => 'string', 'max_len' => 32],
				'comment'      => ['required' => false, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);
			$all = isset($_POST['all']);

			$content_type = integrations\BannedContentType::tryFrom($_POST['content_type']);
			if ($content_type === null) {
				throw new \AppException('index', 'route', 'select a content type for the file hash', SC_BAD_REQUEST);
			}

			// execute edit file scanner hash
			$status = funcs_manage_edit_file_scanner_hash($id, $content_type, $_POST['comment'] ?? '', $all);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/delete_file_scanner_hash', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_deletefilescannerhashform($vars);
		});

		function handle_manage_deletefilescannerhashform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id' => ['required' => true, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);
			$all = isset($_POST['all']);

			// execute delete file scanner hash
			$status = funcs_manage_delete_file_scanner_hash($id, $all);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		/**
		 * Parses the request fields shared by the post filter create and edit forms, throws on invalid input.
		 */
		function parse_manage_postfilterform(array $input): array {
			// validate request fields (byte lengths, character lengths are validated by funcs_manage_validate_post_filter)
			funcs_common_validate_fields($input, [
				'board_id'    => ['required' => false, 'type' => 'string', 'max_len' => 8],
				'match_type'  => ['required' => true, 'type' => 'string', 'max_len' => 16],
				'pattern'     => ['required' => true, 'type' => 'string', 'max_len' => 1024],
				'action'      => ['required' => true, 'type' => 'string', 'max_len' => 16],
				'replacement' => ['required' => false, 'type' => 'string', 'max_len' => 1024],
				'duration'    => ['required' => false, 'type' => 'string', 'max_len' => 16],
				'reason'      => ['required' => false, 'type' => 'string', 'max_len' => 1024],
				'comment'     => ['required' => false, 'type' => 'string', 'max_len' => 1024]
			]);

			// parse request fields
			$match_type = core\PostFilterMatchType::tryFrom($input['match_type']);
			if ($match_type === null) {
				throw new \AppException('index', 'route', 'select a match type for the filter', SC_BAD_REQUEST);
			}

			$action = core\PostFilterAction::tryFrom($input['action']);
			if ($action === null) {
				throw new \AppException('index', 'route', 'select an action for the filter', SC_BAD_REQUEST);
			}

			// ban duration in minutes, may be left empty when the action is not a ban
			$duration = (isset($input['duration']) && trim($input['duration']) !== '')
				? funcs_common_parse_input_int($input, 'duration', 60, 5, 60 * 24 * 365) * 60
				: null;

			return [
				'board_id'    => $input['board_id'] ?? '',
				'match_type'  => $match_type,
				'pattern'     => $input['pattern'],
				'action'      => $action,
				'replacement' => $input['replacement'] ?? '',
				'duration'    => $duration,
				'reason'      => $input['reason'] ?? '',
				'comment'     => $input['comment'] ?? '',
				'enabled'     => isset($input['enabled']),
			];
		}

		$router->add_route(HTTP_POST, '/manage/create_filter', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] > MB_ROLE_ADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_createfilterform($vars);
		});

		function handle_manage_createfilterform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate and parse request fields
			$input = parse_manage_postfilterform($_POST);

			// execute create filter
			$status = funcs_manage_create_post_filter($input);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/edit_filter', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] > MB_ROLE_ADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_editfilterform($vars);
		});

		function handle_manage_editfilterform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id' => ['required' => true, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);
			$input = parse_manage_postfilterform($_POST);

			// execute edit filter
			$status = funcs_manage_edit_post_filter($id, $input);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/delete_filter', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] > MB_ROLE_ADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			return handle_manage_deletefilterform($vars);
		});

		function handle_manage_deletefilterform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'id' => ['required' => true, 'type' => 'string']
			]);

			// parse request fields
			$id = funcs_common_parse_input_int($_POST, 'id', null, 0);

			// execute delete filter
			$status = funcs_manage_delete_post_filter($id);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		// NOTE: config is superadmin only by default, it holds secrets and raw HTML, and replaces editing the old config.php
		$require_superadmin = function () {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}
		};

		$router->add_route(HTTP_POST, '/manage/config_set', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_configsetform($vars);
		});

		function handle_manage_configsetform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id' => ['required' => true, 'type' => 'string', 'max_len' => 8],
				'key'      => ['required' => true, 'type' => 'string', 'max_len' => 64],
			]);

			// validate access to the key
			if (!funcs_manage_config_can_edit($_POST['board_id'], $_POST['key'])) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			// execute config set
			$status = funcs_manage_config_set($_POST['board_id'], $_POST['key'], $_POST);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/config_reset', function ($vars) {
			if (!funcs_common_is_logged_in()) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			return handle_manage_configresetform($vars);
		});

		function handle_manage_configresetform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id' => ['required' => true, 'type' => 'string', 'max_len' => 8],
				'key'      => ['required' => true, 'type' => 'string', 'max_len' => 64],
			]);

			// validate access to the key
			if (!funcs_manage_config_can_edit($_POST['board_id'], $_POST['key'])) {
				throw new \AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
			}

			// execute config reset
			$status = funcs_manage_config_reset($_POST['board_id'], $_POST['key']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/config_access', function ($vars) use ($require_superadmin) {
			$require_superadmin();
			return handle_manage_configaccessform($vars);
		});

		function handle_manage_configaccessform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'key'      => ['required' => true, 'type' => 'string', 'max_len' => 64],
				'req_role' => ['required' => true, 'type' => 'string', 'max_len' => 3],
			]);

			// parse request fields
			$req_role = funcs_common_parse_input_int($_POST, 'req_role', null);

			// execute config access
			$status = funcs_manage_config_set_access($_POST['key'], $req_role);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/config_create_board', function ($vars) use ($require_superadmin) {
			$require_superadmin();
			return handle_manage_configcreateboardform($vars);
		});

		function handle_manage_configcreateboardform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id'  => ['required' => true, 'type' => 'string', 'max_len' => 8],
				'name'      => ['required' => true, 'type' => 'string', 'max_len' => 256],
				'desc'      => ['required' => false, 'type' => 'string', 'max_len' => 1024],
				'type'      => ['required' => true, 'type' => 'string', 'max_len' => 8],
				'position'  => ['required' => true, 'type' => 'string', 'max_len' => 11],
				'copy_from' => ['required' => false, 'type' => 'string', 'max_len' => 8],
			]);

			// execute create board
			$status = funcs_manage_config_create_board($_POST);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}

		$router->add_route(HTTP_POST, '/manage/config_delete_board', function ($vars) use ($require_superadmin) {
			$require_superadmin();
			return handle_manage_configdeleteboardform($vars);
		});

		function handle_manage_configdeleteboardform($vars) {
			// validate CSRF token
			funcs_common_validate_csrf($_POST);

			// validate request fields
			funcs_common_validate_fields($_POST, [
				'board_id' => ['required' => true, 'type' => 'string', 'max_len' => 8],
			]);

			// execute delete board
			$status = funcs_manage_config_delete_board($_POST['board_id']);

			// set query to return properly
			$query = funcs_common_mutate_query($_GET, 'status', $status);

			header("Location: /manage/?{$query}");
			http_response_code(303);
		}
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
