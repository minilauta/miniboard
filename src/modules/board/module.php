<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/database.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __DIR__ . '/funcs_board.php';

class BoardModule implements core\Module
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
		$router->add_route(HTTP_GET, '/:board_id', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);
			$board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
			$board_threads_per_page = $board_cfg['threads_per_page'];
			$board_posts_per_preview = $board_cfg['posts_per_preview'];

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_threads($session_id, $user_role, $board_query_id, true, $board_threads_per_page * $query_page, $board_threads_per_page, false);

			// get replies
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies'] = select_replies_preview($session_id, $thread['board_id'], $thread['post_id'], 0, $board_posts_per_preview);
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false);
			}

			// get thread count
			$threads_n = count_threads($session_id, $board_query_id, false);

			echo $this->renderer->render(__DIR__ . '/templates/board.phtml', [
				'board' => $board_cfg,
				'threads' => $threads,
				'page' => $query_page,
				'page_n' => ceil($threads_n / $board_threads_per_page)
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/catalog', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);
			$board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
			$board_threads_per_catalog_page = $board_cfg['threads_per_catalog_page'];

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_threads($session_id, $user_role, $board_query_id, true, $board_threads_per_catalog_page * $query_page, $board_threads_per_catalog_page, false);

			// get thread metadata
			// TODO: file counts, etc...
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false);
			}

			// get thread count
			$threads_n = count_threads($session_id, $board_query_id, false);

			echo $this->renderer->render(__DIR__ . '/templates/catalog.phtml', [
				'board' => $board_cfg,
				'threads' => $threads,
				'page' => $query_page,
				'page_n' => ceil($threads_n / $board_threads_per_catalog_page)
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/hidden', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);
			$board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
			$board_threads_per_page = $board_cfg['threads_per_page'];

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_threads($session_id, $user_role, $board_query_id, true, $board_threads_per_page * $query_page, $board_threads_per_page, true);

			// do not show replies for hidden threads
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies'] = [];
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false);
			}

			// get thread count
			$threads_n = count_threads($session_id, $board_query_id, true);

			echo $this->renderer->render(__DIR__ . '/templates/hidden.phtml', [
				'board' => $board_cfg,
				'threads' => $threads,
				'page' => $query_page,
				'page_n' => ceil($threads_n / $board_threads_per_page)
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:thread_id', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// get thread
			$thread = select_post($board_cfg['id'], $vars['thread_id']);
			if ($thread == null) {
				throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
			} else if ($thread['parent_id'] != null) {
				throw new \AppException('index', 'route', 'not a valid thread', SC_NOT_FOUND);
			}

			// get replies
			$thread['replies'] = select_posts($session_id, $user_role, $thread['board_id'], $thread['post_id'], false, 0, 9001);

			echo $this->renderer->render(__DIR__ . '/templates/thread.phtml', [
				'board' => $board_cfg,
				'thread' => $thread
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:thread_id/replies', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_post_id_after = funcs_common_parse_input_int($query_params, 'post_id_after', 0, 0, null);

			// get thread
			$thread = select_post($board_cfg['id'], $vars['thread_id']);
			if ($thread == null) {
				throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
			} else if ($thread['parent_id'] != null) {
				throw new \AppException('index', 'route', 'not a valid thread', SC_NOT_FOUND);
			}

			// get replies
			$posts = select_replies_after($session_id, $user_role, $thread['board_id'], $thread['post_id'], $query_post_id_after, false, 0, 9001);

			echo $this->renderer->render(__DIR__ . '/templates/replies.phtml', [
				'board' => $board_cfg,
				'posts' => $posts
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:post_id/report', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			$user_role = funcs_common_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get post
			$post = select_post($board_cfg['id'], $vars['post_id']);
			if ($post == null) {
				throw new \AppException('index', 'route', "post with ID /{$board_cfg['id']}/{$vars['post_id']} not found", SC_NOT_FOUND);
			}

			echo $this->renderer->render(__DIR__ . '/templates/report.phtml', [
				'board' => $board_cfg,
				'post' => $post
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:post_id/download', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get post
			$post = select_post($board_cfg['id'], $vars['post_id']);
			if ($post == null) {
				throw new \AppException('index', 'route', "post with ID /{$board_cfg['id']}/{$vars['post_id']} not found", SC_NOT_FOUND);
			}

			// check file exists
			$file_path = __PUBLIC__ . $post['file'];
			if (!file_exists($file_path)) {
				throw new \AppException('index', 'route', "file with ID /{$board_cfg['id']}/{$vars['post_id']} not found", SC_NOT_FOUND);
			}

			header('Content-Type:' . mime_content_type($file_path));
			header('Content-Disposition: attachment; filename="' . $post['file_original'] . '"');
			readfile($file_path);
		});

		$router->add_route(HTTP_POST, '/:board_id', function ($vars) {
			$error_code = null;
			$error_message = null;
			
			try {
				$result = $this->handle_postform($vars, 'board');
				foreach ($result['headers'] as $key => $val) {
					header("{$key}:{$val}");
				}
				http_response_code($result['status']);
				echo $result['body'];
				return;
			} catch (\AppException $ex) {
				$error_code = $ex->getCode();
				$error_message = $ex->getMessage();
			} catch (\DbException $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			} catch (\Exception $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			}
			
			header('Content-Type:application/json');
			http_response_code($error_code);
			echo json_encode(['error_message' => $error_message]);
		});

		$router->add_route(HTTP_POST, '/:board_id/delete', function ($vars) {
			$error_code = null;
			$error_message = null;
			
			try {
				$result = $this->handle_deleteform($vars);
				foreach ($result['headers'] as $key => $val) {
					header("{$key}:{$val}");
				}
				http_response_code($result['status']);
				echo $result['body'];
				return;
			} catch (\AppException $ex) {
				$error_code = $ex->getCode();
				$error_message = $ex->getMessage();
			} catch (\DbException $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			} catch (\Exception $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			}
			
			header('Content-Type:application/json');
			http_response_code($error_code);
			echo json_encode(['error_message' => $error_message]);
		});

		$router->add_route(HTTP_POST, '/:board_id/:thread_id', function ($vars) {
			$error_code = null;
			$error_message = null;
			
			try {
				$result = $this->handle_postform($vars, 'thread');
				foreach ($result['headers'] as $key => $val) {
					header("{$key}:{$val}");
				}
				http_response_code($result['status']);
				echo $result['body'];
				return;
			} catch (\AppException $ex) {
				$error_code = $ex->getCode();
				$error_message = $ex->getMessage();
			} catch (\DbException $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			} catch (\Exception $ex) {
				$error_code = 500;
				$error_message = $ex->getMessage();
			}
			
			header('Content-Type:application/json');
			http_response_code($error_code);
			echo json_encode(['error_message' => $error_message]);
		});

		$router->add_route(HTTP_POST, '/:board_id/:post_id/hide', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}
			$session_id = session_id();

			// toggle hide
			$hide = select_hide($session_id, $board_cfg['id'], $vars['post_id']);
			if ($hide == null) {
				$hide = funcs_board_create_hide($session_id, $board_cfg['id'], $vars['post_id']);
				insert_hide($hide);
			} else {
				delete_hide($hide);
			}
			
			http_response_code(200);
		});

		$router->add_route(HTTP_POST, '/:board_id/:post_id/report', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// validate request fields
			funcs_board_validate_report($_POST, MB_REPORT_TYPES);

			// validate captcha
			if (MB_CAPTCHA_REPORT) {
				funcs_common_validate_captcha($_POST);
			}

			// get post
			$post = select_post($board_cfg['id'], $vars['post_id']);
			if ($post == null) {
				throw new \AppException('index', 'route', "post with ID /{$board_cfg['id']}/{$vars['post_id']} not found", SC_NOT_FOUND);
			}

			// create report
			$ip = funcs_common_get_client_remote_address(MB_CLOUDFLARE, $_SERVER);
			$report = funcs_board_create_report($ip, $board_cfg['id'], $post['post_id'], $_POST['type'], MB_REPORT_TYPES);

			// insert report
			$inserted_report_id = insert_report($report);

			http_response_code(200);
			echo "Post reported, report ID: {$inserted_report_id}";
		});

		$router->add_route(HTTP_GET, '/:board_id/:thread_id/:post_id', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
				echo $this->renderer->render(__DIR__ . '/templates/components/post_preview_null.phtml', [
					'error_code' => 401,
					'error_title' => 'Unauthorized',
					'message' => "post with ID /{$board_cfg['id']}/{$vars['thread_id']}/{$vars['post_id']} access denied"
				]);
				return;
			}

			// get post
			$post = select_post($board_cfg['id'], $vars['post_id']);
			if ($post == null || ($post['parent_id'] != null && $post['parent_id'] != $vars['thread_id'])) {
				echo $this->renderer->render(__DIR__ . '/templates/components/post_preview_null.phtml', [
					'error_code' => 404,
					'error_title' => 'Not Found',
					'message' => "post with ID /{$board_cfg['id']}/{$vars['thread_id']}/{$vars['post_id']} not found"
				]);
				return;
			}

			echo $this->renderer->render(__DIR__ . '/templates/components/' . ($post['parent_id'] == null ? 'post_preview_op.phtml' : 'post_preview_reply.phtml'), [
				'post' => $post
			]);
		});
	}

	private function handle_postform(array $vars, string $context) {
		// parse request body
		$file = null;
		if (isset($_FILES['file'])) {
			$file = $_FILES['file'];
		}
	
		// get board config
		$board_cfg = funcs_common_get_board_cfg($vars['board_id']);
	
		// check board access
		if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
			throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
		}
	
		// get user info
		$user_ip = funcs_common_get_client_remote_address(MB_CLOUDFLARE, $_SERVER);
		$user_country = null;
		if ($board_cfg['flags'] == true) {
			$user_country = funcs_common_get_client_remote_country(MB_CLOUDFLARE, $_SERVER);
		}
		$user_last_post_by_ip = select_last_post_by_ip($user_ip);
		$user_is_logged_in = funcs_common_is_logged_in();
	
		// clean some request fields (UNICODE icons)
		$_POST['name'] = funcs_common_clean_unicode($_POST['name']);
		$_POST['email'] = funcs_common_clean_unicode($_POST['email']);
		$_POST['subject'] = funcs_common_clean_unicode($_POST['subject']);
		$_POST['message'] = funcs_common_clean_unicode($_POST['message']);
	
		// validate request fields
		funcs_common_validate_fields($_POST, $board_cfg['fields_post']);
	
		// if board type is 'main', get target board config
		if ($board_cfg['type'] === 'main') {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($_POST['board']);
		
			// do not allow posting directly on boards of type 'main'
			if ($board_cfg['type'] === 'main') {
				throw new \AppException('index', 'route', "posting on /{$board_cfg['id']}/ board is disabled", SC_BAD_REQUEST);
			}
		
			// validate request fields
			funcs_common_validate_fields($_POST, $board_cfg['fields_post']);
		}
	
		// validate captcha (skip for logged in users)
		if (!$user_is_logged_in) {
			if (MB_CAPTCHA_THREAD && $context === 'board' || MB_CAPTCHA_REPLY && $context === 'thread') {
				funcs_common_validate_captcha($_POST);
			}
		}
	
		// validate delay (skip for logged in users)
		if (!$user_is_logged_in && MB_DELAY > 0 && $user_last_post_by_ip != null) {
			$delay_in_seconds = time() - $user_last_post_by_ip['timestamp'];
			$cooldown_in_seconds = MB_DELAY - $delay_in_seconds;
			if ($delay_in_seconds < MB_DELAY) {
				throw new \AppException('index', 'route', "please wait a moment before posting again, you will be able to post in {$cooldown_in_seconds}s", SC_FORBIDDEN);
			}
		}
	
		// cleanup expired bans
		cleanup_bans();
	
		// check if ip address has been banned
		$ban = select_ban($user_ip);
		if ($ban && !$user_is_logged_in) {
			$ban_expires = date(MB_DATEFORMAT, $ban['expire']);
			throw new \AppException('index', 'route', "this ip address has been banned for reason: {$ban['reason']}. the ban will expire on {$ban_expires}", SC_FORBIDDEN);
		}
	
		// get thread if replying
		$thread_id = null;
		if (isset($vars['thread_id'])) {
			$parent = select_post($board_cfg['id'], $vars['thread_id']);
			if ($parent != null && $parent['parent_id'] != null) {
				throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
			} else if ($parent != null) {
				if ($parent['locked'] !== 0 && !$user_is_logged_in) {
					throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} is locked", SC_FORBIDDEN);
				}
		
				$thread_id = $parent['post_id'];
			}
		}
	
		if (!isset($board_cfg['text']) || $board_cfg['text'] == false) {
			// validate request file
			$embed = strlen(trim($_POST['embed'])) > 0;
			$spoiler = isset($_POST['spoiler']) && $_POST['spoiler'] == true ? true : false;
			$no_file_ok = ($thread_id != null || $embed) ? true : $board_cfg['nofileok'];
			$file_info = funcs_board_validate_upload($file, $no_file_ok, $spoiler, $board_cfg['mime_ext_types'], $board_cfg['maxkb'] * 1000);
			$is_file_or_embed = $embed || $file_info != null;
		
			// validate request message + file or embed
			if (strlen(trim($_POST['message'])) === 0 && !$is_file_or_embed) {
				throw new \AppException('index', 'route', 'message and file or embed cannot both be null', SC_BAD_REQUEST);
			}
		
			// check md5 file collisions
			$file_collisions = [];
			if ($file_info != null) {
				$file_collisions = select_files_by_md5($file_info['md5']);
			}
		
			// upload file or embed url
			if (!$embed) {
				$file = funcs_board_execute_upload($file_info, $file_collisions, $spoiler, $board_cfg['max_width'], $board_cfg['max_height']);
			} else {
				$file = funcs_board_execute_embed($_POST['embed'], $board_cfg['embed_types'], $board_cfg['max_width'], $board_cfg['max_height']);
				$file_info = null;
			}
		} else {
			// validate request message
			if (strlen(trim($_POST['message'])) === 0) {
				throw new \AppException('index', 'route', 'message cannot be null', SC_BAD_REQUEST);
			}
		
			$file = [
				'file'                => '',
				'file_rendered'       => '',
				'file_hex'            => '',
				'file_original'       => '',
				'file_size'           => 0,
				'file_size_formatted' => '',
				'file_mime'           => null,
				'image_width'         => 0,
				'image_height'        => 0,
				'thumb'               => '',
				'thumb_width'         => 0,
				'thumb_height'        => 0,
				'audio_album'         => null,
				'embed'               => 0
			];
			$file_info = null;
		}
	
		// create post
		$post = funcs_board_create_post($user_ip, $user_country, $board_cfg, $thread_id, isset($parent) ? $parent['salt'] : null, $file_info, $file, $_POST);
	
		// generate unique post_id on current board
		init_post_auto_increment($post['board_id']);
		$post['post_id'] = generate_post_auto_increment($post['board_id']);
	
		// insert post
		$inserted_post_id = insert_post($post);
	
		// bump thread
		$email_split = array_map(fn($val): string => strtolower($val), explode(' ', $post['email']));
		$thread_bumped = false;
		if ($post['parent_id'] != null && !in_array('sage', $email_split)) {
			$thread_replies_n = count_posts('NULL', $post['board_id'], $post['parent_id'], false);
			if ($thread_replies_n <= $board_cfg['max_replies']) {
				$thread_bumped = bump_thread($post['board_id'], $post['parent_id']);
			}
		}
	
		// cleanup board (100 threads at a time)
		if ($thread_id == null && isset($board_cfg['max_threads']) && $board_cfg['max_threads'] > 0) {
			// select all threads that exceed 'max_threads'
			$threads_to_be_deleted = select_threads_past_offset($post['board_id'], $board_cfg['max_threads'], 100);
		
			// delete selected threads along with replies, files, etc...
			foreach ($threads_to_be_deleted as &$thread) {
				funcs_common_delete_post($thread['board_id'], $thread['post_id']);
			}
		}
	
		// anonymize board
		if (MB_ANONYMIZE_AFTER > 0) {
			anonymize_posts_after(MB_ANONYMIZE_AFTER);
		}
	
		// check for CSAM
		if (isset($file_info) && isset($file)) {
			$csam_scan_result = funcs_board_csam_scanner_check($file);
			if (isset($csam_scan_result) && isset($csam_scan_result['match']) && $csam_scan_result['match'] === true) {
				// create and insert automatic report
				$csam_match_similarity = round($csam_scan_result['similarity'] * 100.0, 2);
				$csam_match_log_msg = "CSAM-scanner: detected as CP, similarity: {$csam_match_similarity}%";
				insert_report([
					'ip'        => '127.0.0.1',
					'timestamp' => time(),
					'board_id'  => $board_cfg['id'],
					'post_id'   => $inserted_post_id,
					'type'      => $csam_match_log_msg
				]);
				insert_log('127.0.0.1', time(), 'CSAM-scanner', $csam_match_log_msg);
		
				// delete the post automatically
				delete_post($board_cfg['id'], $inserted_post_id);
			}
		}
	
		// handle noko
		$redirect_url = '/' . $board_cfg['id'] . '/';
		if (in_array('noko', $email_split) || $board_cfg['alwaysnoko']) {
			$redirect_url .= ($post['parent_id'] == null ? $inserted_post_id : $post['parent_id']) . '/#' . $post['board_id'] . '-' . $inserted_post_id;
		}

		return [
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'status' => 200,
			'body' => json_encode(['redirect_url' => $redirect_url]),
		];
	}

	private function handle_deleteform(array $vars) {
		// get board config
		$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

		// check board access
		if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
			throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
		}

		// validate request fields
		funcs_common_validate_fields($_POST, [
			'password'  => ['required' => true, 'type' => 'string', 'max_len' => 64],
			'delete'    => ['required' => true, 'type' => 'array']
		]);

		// loop over each post, check pass and mark as deleted
		foreach ($_POST['delete'] as $val) {
			// parse board id and post id
			$delete_parsed = explode('/', $val);
			$delete_board_id = $delete_parsed[0];
			$delete_post_id = intval($delete_parsed[1]);

			// get board config
			$board_cfg = funcs_common_get_board_cfg($delete_board_id);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_common_get_role())) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get post
			$post = select_post($delete_board_id, $delete_post_id);
			if ($post == null || $post['password'] == null) {
				continue;
			}

			// verify password
			if (funcs_common_verify_password($_POST['password'], $post['password']) !== true) {
				throw new \AppException('index', 'route', "invalid password for post with ID /{$delete_board_id}/{$delete_post_id}", SC_FORBIDDEN);
			}

			// validate timeframe if post is a thread
			if ($post['parent_id'] == null) {
				$timeframe_reply_limit = MB_TIMEFRAME_REPLY_LIMIT;
				$thread_replies_n = count_posts('NULL', $post['board_id'], $post['post_id'], false);
				if (MB_TIMEFRAME > 0 && $thread_replies_n > $timeframe_reply_limit) {
					$timeframe_in_seconds = MB_TIMEFRAME;
					if (time() - $post['timestamp'] > $timeframe_in_seconds) {
						throw new \AppException('index', 'route', "you cannot delete threads older than {$timeframe_in_seconds}s with more than {$timeframe_reply_limit} replies", SC_FORBIDDEN);
					}
				}
			}

			// delete post
			$warnings = funcs_common_delete_post($post['board_id'], $post['post_id']);
			if ($warnings) {
				throw new \AppException('index', 'route', "failed to delete post with ID /{$delete_board_id}/{$delete_post_id}", SC_INTERNAL_ERROR);
			}

			// debump if deleted post was a reply
			$thread_bumped = false;
			if ($post['parent_id'] != null) {
				$thread_replies_n = count_posts('NULL', $post['board_id'], $post['parent_id'], false);
				if ($thread_replies_n <= $board_cfg['max_replies']) {
					$thread_bumped = bump_thread($post['board_id'], $post['parent_id']);
				}
			}
		}

		return [
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'status' => 200,
			'body' => json_encode(['redirect_url' => '/' . $board_cfg['id'] . '/']),
		];
	}

	public function get_name(): string
	{
		return 'board';
	}

	public function get_index(): string
	{
		return '';
	}
}

return new BoardModule();
