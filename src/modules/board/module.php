<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/core/cache.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/database.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __DIR__ . '/funcs_board.php';

class BoardModule implements core\Module
{
	private core\HtmlRenderer $renderer;
	private core\FileCache $cache;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
		$this->cache = new core\FileCache("module_board");
	}

	public function __destruct()
	{

	}

	public function register_middleware(Closure $handler): void
	{
		
	}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/:board_id', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);
			$board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
			$board_threads_per_page = $board_cfg['threads_per_page'];
			$board_posts_per_preview = $board_cfg['posts_per_preview'];

			// check board access
			$user_role = funcs_board_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_posts(session_id(), $user_role, $board_query_id, 0, true, $board_threads_per_page * $query_page, $board_threads_per_page, false);

			// get replies
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies'] = select_posts_preview('NULL', $thread['board_id'], $thread['post_id'], 0, $board_posts_per_preview);
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false, false);
			}

			// get thread count
			$threads_n = count_posts(session_id(), $board_query_id, 0, false);

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
			$user_role = funcs_board_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_posts(session_id(), $user_role, $board_query_id, 0, true, $board_threads_per_catalog_page * $query_page, $board_threads_per_catalog_page, false);

			// get thread metadata
			// TODO: file counts, etc...
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false);
			}

			// get thread count
			$threads_n = count_posts(session_id(), $board_query_id, 0, false);

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
			$user_role = funcs_board_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

			// get threads
			$threads = select_posts(session_id(), $user_role, $board_query_id, 0, true, $board_threads_per_page * $query_page, $board_threads_per_page, true);

			// do not show replies for hidden threads
			foreach ($threads as $key => $thread) {
				$threads[$key]['replies'] = [];
				$threads[$key]['replies_n'] = count_posts('NULL', $thread['board_id'], $thread['post_id'], false, false);
			}

			// get thread count
			$threads_n = count_posts(session_id(), $board_query_id, 0, true);

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
			$user_role = funcs_board_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get thread
			$thread = select_post($board_cfg['id'], $vars['thread_id']);
			if ($thread == null) {
				throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
			} else if ($thread['parent_id'] !== 0) {
				throw new \AppException('index', 'route', 'not a valid thread', SC_BAD_REQUEST);
			}

			// get replies
			$thread['replies'] = select_posts(session_id(), $user_role, $thread['board_id'], $thread['post_id'], false, 0, 9001, false);

			echo $this->renderer->render(__DIR__ . '/templates/thread.phtml', [
				'board' => $board_cfg,
				'thread' => $thread
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:thread_id/replies', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			$user_role = funcs_board_get_role();
			if (!funcs_board_check_access($board_cfg, $user_role)) {
				throw new \AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
			}

			// get query params
			$query_params = funcs_common_parse_query_str($_SERVER);
			$query_post_id_after = funcs_common_parse_input_int($query_params, 'post_id_after', 0, 0, null);

			// get thread
			$thread = select_post($board_cfg['id'], $vars['thread_id']);
			if ($thread == null) {
				throw new \AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
			} else if ($thread['parent_id'] !== 0) {
				throw new \AppException('index', 'route', 'not a valid thread', SC_BAD_REQUEST);
			}

			// get replies
			$posts = select_replies_after($user_role, $thread['board_id'], $thread['post_id'], $query_post_id_after, false, 0, 9001, false);

			echo $this->renderer->render(__DIR__ . '/templates/replies.phtml', [
				'board' => $board_cfg,
				'posts' => $posts
			]);
		});

		$router->add_route(HTTP_GET, '/:board_id/:post_id/report', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			$user_role = funcs_board_get_role();
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
			if (!funcs_board_check_access($board_cfg, funcs_board_get_role())) {
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

		$router->add_route(HTTP_GET, '/:board_id/:thread_id/:post_id', function ($vars) {
			// get board config
			$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

			// check board access
			if (!funcs_board_check_access($board_cfg, funcs_board_get_role())) {
				echo $this->renderer->render(__DIR__ . '/templates/components/post_preview_null.phtml', [
				'error_code' => 401,
				'error_title' => 'Unauthorized',
				'message' => "post with ID /{$board_cfg['id']}/{$vars['thread_id']}/{$vars['post_id']} access denied"
				]);
				return;
			}

			// get post
			$post = select_post($board_cfg['id'], $vars['post_id']);
			if ($post == null || ($post['parent_id'] !== 0 && $post['parent_id'] != $vars['thread_id'])) {
				echo $this->renderer->render(__DIR__ . '/templates/components/post_preview_null.phtml', [
				'error_code' => 404,
				'error_title' => 'Not Found',
				'message' => "post with ID /{$board_cfg['id']}/{$vars['thread_id']}/{$vars['post_id']} not found"
				]);
				return;
			}

			echo $this->renderer->render(__DIR__ . '/templates/components/' . ($post['parent_id'] === 0 ? 'post_preview_op.phtml' : 'post_preview_reply.phtml'), [
				'post' => $post
			]);
		});
	}

	public function get_name(): string
	{
		return 'board';
	}
}

return new BoardModule();
