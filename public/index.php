<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funcs_common.php';
require_once __DIR__ . '/funcs_board.php';
require_once __DIR__ . '/funcs_manage.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->add(new Middlewares\TrailingSlash(true));
$app->add($session_middleware);

$app->get('/', function (Request $request, Response $response, array $args) {
  $renderer = new PhpRenderer('templates/', [
    'site_name' => MB_SITE_NAME,
    'site_desc' => MB_SITE_DESC,
    'site_stats' => select_site_stats()
  ]);
  return $renderer->render($response, 'root.phtml');
});

$app->get('/manage/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    $renderer = new PhpRenderer('templates/', []);
    return $renderer->render($response, 'login.phtml');
  }
  
  // get query params
  $query_params = $request->getQueryParams();
  $query_route = funcs_common_parse_input_str($query_params, 'route', '');
  $query_status = funcs_common_parse_input_str($query_params, 'status', '');
  $query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

  // render page
  $renderer = new PhpRenderer('templates/', [
    'route' => $query_route,
    'status' => $query_status,
    'page' => $query_page,
  ]);
  return $renderer->render($response, 'manage.phtml');
});

$app->get('/manage/logout/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_logout()) {
    throw new AppException('index', 'route', "logout failed to clear PHP session", SC_INTERNAL_ERROR);
  }

  $response = $response
    ->withHeader('Location', '/')
    ->withStatus(303);
  return $response;
});

$app->post('/manage/login/', function (Request $request, Response $response, array $args) {
  return handle_loginform($request, $response, $args);
});

function handle_loginform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'username'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 75],
    'password'  => ['required' => true, 'type' => 'string', 'min_len' => 2, 'max_len' => 256]
  ]);

  // validate captcha
  if (MB_CAPTCHA_LOGIN) {
    funcs_common_validate_captcha($params);
  }

  // get account
  $account = select_account($params['username']);
  if ($account == null) {
    throw new AppException('index', 'route', "invalid username or password for user '{$params['username']}'", SC_UNAUTHORIZED);
  }

  // attempt to login
  $login = funcs_manage_login($account, $params['password']);
  if ($login !== true) {
    throw new AppException('index', 'route', "invalid username or password for user '{$params['username']}'", SC_UNAUTHORIZED);
  }

  // update lastactive
  $account['lastactive'] = time();
  if (update_account($account) !== TRUE) {
    throw new AppException('index', 'route', "failed to update account info for user {$params['username']}", SC_INTERNAL_ERROR);
  }

  $response = $response
    ->withHeader('Location', '/manage/')
    ->withStatus(303);
  return $response;
}

$app->post('/manage/import/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
    throw new AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
  }

  return handle_importform($request, $response, $args);
});

function handle_importform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'db_name'    => ['required' => true, 'type' => 'string'],
    'db_user'    => ['required' => true, 'type' => 'string'],
    'db_pass'    => ['required' => true, 'type' => 'string'],
    'table_name' => ['required' => true, 'type' => 'string'],
    'table_type' => ['required' => true, 'type' => 'string'],
    'board_id'   => ['required' => true, 'type' => 'string']
  ]);

  // execute import
  $status = funcs_manage_import($params);

  $response = $response
    ->withHeader('Location', "/manage/?route=import&status={$status}")
    ->withStatus(303);
  return $response;
}

$app->post('/manage/rebuild/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  if ($_SESSION['mb_role'] !== MB_ROLE_SUPERADMIN) {
    throw new AppException('index', 'route', 'insufficient permissions', SC_FORBIDDEN);
  }

  return handle_rebuildform($request, $response, $args);
});

function handle_rebuildform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'board_id'   => ['required' => true, 'type' => 'string']
  ]);

  // execute rebuild
  $status = funcs_manage_rebuild($params);

  $response = $response
    ->withHeader('Location', "/manage/?route=rebuild&status={$status}")
    ->withStatus(303);
  return $response;
}

$app->post('/manage/delete/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  return handle_manage_deleteform($request, $response, $args);
});

function handle_manage_deleteform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'select'   => ['required' => true, 'type' => 'array']
  ]);

  // execute delete
  $status = funcs_manage_delete($params['select']);

  // set query to return properly
  $query = funcs_common_mutate_query($_GET, 'status', $status);

  $response = $response
    ->withHeader('Location', "/manage/?{$query}")
    ->withStatus(303);
  return $response;
}

$app->post('/manage/approve/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  return handle_manage_approveform($request, $response, $args);
});

function handle_manage_approveform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'select'   => ['required' => true, 'type' => 'array']
  ]);

  // execute approve
  $status = funcs_manage_approve($params['select']);

  // set query to return properly
  $query = funcs_common_mutate_query($_GET, 'status', $status);

  $response = $response
    ->withHeader('Location', "/manage/?{$query}")
    ->withStatus(303);
  return $response;
}

$app->post('/manage/toggle_lock/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  return handle_manage_togglelockform($request, $response, $args);
});

function handle_manage_togglelockform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'select'   => ['required' => true, 'type' => 'array']
  ]);

  // execute toggle lock
  $status = funcs_manage_toggle_lock($params['select']);

  // set query to return properly
  $query = funcs_common_mutate_query($_GET, 'status', $status);

  $response = $response
    ->withHeader('Location', "/manage/?{$query}")
    ->withStatus(303);
  return $response;
}

$app->post('/manage/toggle_sticky/', function (Request $request, Response $response, array $args) {
  if (!funcs_manage_is_logged_in()) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  return handle_manage_togglestickyform($request, $response, $args);
});

function handle_manage_togglestickyform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_common_validate_fields($params, [
    'select'   => ['required' => true, 'type' => 'array']
  ]);

  // execute toggle sticky
  $status = funcs_manage_toggle_sticky($params['select']);

  // set query to return properly
  $query = funcs_common_mutate_query($_GET, 'status', $status);

  $response = $response
    ->withHeader('Location', "/manage/?{$query}")
    ->withStatus(303);
  return $response;
}

$app->get('/{board_id}/{post_id}/report/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  // get post
  $post = select_post($board_cfg['id'], $args['post_id']);
  if ($post == null) {
    throw new AppException('index', 'route', "post with ID /{$board_cfg['id']}/{$args['post_id']} not found", SC_NOT_FOUND);
  }

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'post' => $post
  ]);
  return $renderer->render($response, 'report.phtml');
});

$app->post('/{board_id}/{post_id}/hide/', function (Request $request, Response $response, array $args) {
  // parse request body
  $params = (array) $request->getParsedBody();

  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  // toggle hide
  $hide = select_hide(session_id(), $board_cfg['id'], $args['post_id']);
  if ($hide == null) {
    $hide = funcs_board_create_hide(session_id(), $board_cfg['id'], $args['post_id']);
    insert_hide($hide);
  } else {
    delete_hide($hide);
  }

  $response = $response->withStatus(200);
  return $response;
});

$app->post('/{board_id}/{post_id}/report/', function (Request $request, Response $response, array $args) {
  return handle_reportform($request, $response, $args);
});

function handle_reportform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  // validate request fields
  funcs_board_validate_report($params, MB_REPORT_TYPES);

  // validate captcha
  if (MB_CAPTCHA_REPORT) {
    funcs_common_validate_captcha($params);
  }

  // get post
  $post = select_post($board_cfg['id'], $args['post_id']);
  if ($post == null) {
    throw new AppException('index', 'route', "post with ID /{$board_cfg['id']}/{$args['post_id']} not found", SC_NOT_FOUND);
  }

  // create report
  $ip = funcs_common_get_client_remote_address(MB_CLOUDFLARE, $_SERVER);
  $report = funcs_board_create_report($ip, $board_cfg['id'], $post['post_id'], $params['type'], MB_REPORT_TYPES);

  // insert report
  $inserted_report_id = insert_report($report);

  $response->getBody()->write('Post reported');
  $response = $response->withStatus(200);
  return $response;
}

$app->get('/{board_id}/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);
  $board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
  $board_threads_per_page = $board_cfg['threads_per_page'];
  $board_posts_per_preview = $board_cfg['posts_per_preview'];

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }
  $user_role = funcs_manage_get_role();

  // get query params
  $query_params = $request->getQueryParams();
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

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'threads' => $threads,
    'page' => $query_page,
    'page_n' => ceil($threads_n / $board_threads_per_page)
  ]);
  return $renderer->render($response, 'board.phtml');
});

$app->get('/{board_id}/hidden/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);
  $board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
  $board_threads_per_page = $board_cfg['threads_per_page'];

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }
  $user_role = funcs_manage_get_role();

  // get query params
  $query_params = $request->getQueryParams();
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

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'threads' => $threads,
    'page' => $query_page,
    'page_n' => ceil($threads_n / $board_threads_per_page)
  ]);
  return $renderer->render($response, 'hidden.phtml');
});

$app->get('/{board_id}/catalog/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);
  $board_query_id = $board_cfg['type'] !== 'main' ? $board_cfg['id'] : null;
  $board_threads_per_catalog_page = $board_cfg['threads_per_catalog_page'];

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }
  $user_role = funcs_manage_get_role();

  // get query params
  $query_params = $request->getQueryParams();
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

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'threads' => $threads,
    'page' => $query_page,
    'page_n' => ceil($threads_n / $board_threads_per_catalog_page)
  ]);
  return $renderer->render($response, 'catalog.phtml');
});

$app->get('/{board_id}/{thread_id}/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }
  $user_role = funcs_manage_get_role();

  // get thread
  $thread = select_post($board_cfg['id'], $args['thread_id']);
  if ($thread == null) {
    throw new AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$args['thread_id']} not found", SC_NOT_FOUND);
  } else if ($thread['parent_id'] !== 0) {
    throw new AppException('index', 'route', 'not a valid thread', SC_BAD_REQUEST);
  }

  // get replies
  $thread['replies'] = select_posts(session_id(), $user_role, $thread['board_id'], $thread['post_id'], false, 0, 9001, false);

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'thread' => $thread
  ]);
  return $renderer->render($response, 'thread.phtml');
});

$app->get('/{board_id}/{thread_id}/{post_id}/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    $renderer = new PhpRenderer('templates/', [
      'error_code' => 401,
      'error_title' => 'Unauthorized',
      'message' => "post with ID /{$board_cfg['id']}/{$args['thread_id']}/{$args['post_id']} access denied"
    ]);
    return $renderer->render($response, 'board/post_preview_null.phtml');
  }

  // get post
  $post = select_post($board_cfg['id'], $args['post_id']);
  if ($post == null || ($post['parent_id'] !== 0 && $post['parent_id'] != $args['thread_id'])) {
    $renderer = new PhpRenderer('templates/', [
      'error_code' => 404,
      'error_title' => 'Not Found',
      'message' => "post with ID /{$board_cfg['id']}/{$args['thread_id']}/{$args['post_id']} not found"
    ]);
    return $renderer->render($response, 'board/post_preview_null.phtml');
  }

  $renderer = new PhpRenderer('templates/', [
    'post' => $post
  ]);
  return $renderer->render($response, $post['parent_id'] === 0 ? 'board/post_preview_op.phtml' : 'board/post_preview_reply.phtml');
});

$app->post('/{board_id}/', function (Request $request, Response $response, array $args) {
  $error_code = null;
  $error_message = null;
  
  try {
    return handle_postform($request, $response, $args, 'board');
  } catch (AppException $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  } catch (DbException $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  } catch (Exception $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  }
  
  $response = $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus($error_code);
  $response->getBody()->write(json_encode([
    'error_message' => $error_message,
  ]));
  return $response;
});

$app->post('/{board_id}/delete/', function (Request $request, Response $response, array $args) {
  return handle_deleteform($request, $response, $args);
});

function handle_deleteform(Request $request, Response $response, array $args): Response {
  // parse request body
  $params = (array) $request->getParsedBody();

  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  // validate request fields
  funcs_common_validate_fields($params, [
    'password'  => ['required' => true, 'type' => 'string', 'max_len' => 64],
    'delete'    => ['required' => true, 'type' => 'array']
  ]);

  // loop over each post, check pass and mark as deleted
  foreach ($params['delete'] as $val) {
    // parse board id and post id
    $delete_parsed = explode('/', $val);
    $delete_board_id = $delete_parsed[0];
    $delete_post_id = intval($delete_parsed[1]);

    // get board config
    $board_cfg = funcs_common_get_board_cfg($delete_board_id);

    // check board access
    if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
      throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
    }

    // get post
    $post = select_post($delete_board_id, $delete_post_id);
    if ($post == null) {
      continue;
    }

    // verify password
    if (funcs_common_verify_password($params['password'], $post['password']) !== true) {
      continue;
    }

    // delete post
    $warnings = funcs_common_delete_post($post['board_id'], $post['post_id']);
    if ($warnings) {
      throw new AppException('index', 'route', "failed to delete post with ID /{$delete_board_id}/{$delete_post_id}", SC_INTERNAL_ERROR);
    }

    // debump if deleted post was a reply
    $thread_bumped = false;
    if ($post['parent_id'] > 0) {
      $thread_replies_n = count_posts('NULL', $post['board_id'], $post['parent_id'], false, false);
      if ($thread_replies_n <= $board_cfg['max_replies']) {
        $thread_bumped = bump_thread($post['board_id'], $post['parent_id']);
      }
    }
  }

  $response = $response
    ->withHeader('Location', '/' . $board_cfg['id'] . '/')
    ->withStatus(303);
  return $response;
}

$app->post('/{board_id}/{thread_id}/', function (Request $request, Response $response, array $args) {
  $error_code = null;
  $error_message = null;
  
  try {
    return handle_postform($request, $response, $args, 'thread');
  } catch (AppException $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  } catch (DbException $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  } catch (Exception $ex) {
    $error_code = $ex->getCode();
    $error_message = $ex->getMessage();
  }
  
  $response = $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus($error_code);
  $response->getBody()->write(json_encode([
    'error_message' => $error_message,
  ]));
  return $response;
});

function handle_postform(Request $request, Response $response, array $args, string $context): Response {
  // parse request body
  $params = (array) $request->getParsedBody();
  $file = $request->getUploadedFiles()['file'];

  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // check board access
  if (!funcs_board_check_access($board_cfg, funcs_manage_get_role())) {
    throw new AppException('index', 'route', 'access denied', SC_UNAUTHORIZED);
  }

  // get user info
  $user_ip = funcs_common_get_client_remote_address(MB_CLOUDFLARE, $_SERVER);
  $user_last_post_by_ip = select_last_post_by_ip($user_ip);
  $user_is_logged_in = funcs_manage_is_logged_in();

  // validate request fields
  funcs_common_validate_fields($params, $board_cfg['fields_post']);

  // if board type is 'main', get target board config
  if ($board_cfg['type'] === 'main') {
    // get board config
    $board_cfg = funcs_common_get_board_cfg($params['board']);

    // do not allow posting directly on boards of type 'main'
    if ($board_cfg['type'] === 'main') {
      throw new AppException('index', 'route', "posting on /{$board_cfg['id']}/ board is disabled", SC_BAD_REQUEST);
    }

    // validate request fields
    funcs_common_validate_fields($params, $board_cfg['fields_post']);
  }

  // validate captcha (skip for logged in users)
  if (!$user_is_logged_in) {
    if (MB_CAPTCHA_THREAD && $context === 'board' || MB_CAPTCHA_REPLY && $context === 'thread') {
      funcs_common_validate_captcha($params);
    }
  }

  // validate delay (skip for logged in users)
  if (!$user_is_logged_in && MB_DELAY > 0 && $user_last_post_by_ip != null) {
    $delay_in_seconds = time() - $user_last_post_by_ip['timestamp'];
    $cooldown_in_seconds = MB_DELAY - $delay_in_seconds;
    if ($delay_in_seconds < MB_DELAY) {
      throw new AppException('index', 'route', "please wait a moment before posting again, you will be able to post in {$cooldown_in_seconds}s", SC_FORBIDDEN);
    }
  }

  // check if ip address has been banned
  $ban = select_ban($user_ip);
  if ($ban) {
    $ban_expires = strftime(MB_DATEFORMAT, $ban['expire']);
    throw new AppException('index', 'route', "this ip address has been banned for reason: {$ban['reason']}. the ban will expire on {$ban_expires}", SC_FORBIDDEN);
  }

  // get thread if replying
  $thread_id = null;
  if (isset($args['thread_id'])) {
    $parent = select_post($board_cfg['id'], $args['thread_id']);
    if ($parent != null && $parent['parent_id'] > 0) {
      throw new AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$args['thread_id']} not found", SC_NOT_FOUND);
    } else if ($parent != null) {
      if ($parent['locked'] !== 0 && !$user_is_logged_in) {
        throw new AppException('index', 'route', "thread with ID /{$board_cfg['id']}/{$args['thread_id']} is locked", SC_FORBIDDEN);
      }

      $thread_id = $parent['post_id'];
    }
  }

  // validate request file
  $embed = strlen(trim($params['embed'])) > 0;
  $spoiler = isset($params['spoiler']) && $params['spoiler'] == true ? true : false;
  $no_file_ok = ($thread_id != null || $embed) ? true : $board_cfg['nofileok'];
  $file_info = funcs_board_validate_upload($file, $no_file_ok, $spoiler, $board_cfg['mime_ext_types'], $board_cfg['maxkb'] * 1000);
  $is_file_or_embed = $embed || $file_info != null;

  // validate request message + file or embed
  if (strlen(trim($params['message'])) === 0 && !$is_file_or_embed) {
    throw new AppException('index', 'route', 'message and file or embed cannot both be null', SC_BAD_REQUEST);
  }

  // check md5 file collisions
  $file_collisions = [];
  if ($file_info != null) {
    $file_collisions = select_files_by_md5($file_info['md5']);
  }

  // upload file or embed url
  if (!$embed) {
    $file = funcs_board_execute_upload($file, $file_info, $file_collisions, $spoiler, $board_cfg['max_width'], $board_cfg['max_height']);
  } else {
    $file = funcs_board_execute_embed($params['embed'], $board_cfg['embed_types'], $board_cfg['max_width'], $board_cfg['max_height']);
    $file_info = null;
  }

  // create post
  $post = funcs_board_create_post($user_ip, $board_cfg, $thread_id, $file_info, $file, $params);

  // generate unique post_id on current board
  init_post_auto_increment($post['board_id']);
  $post['post_id'] = generate_post_auto_increment($post['board_id']);

  // insert post
  $inserted_post_id = insert_post($post);

  // bump thread
  $email_split = array_map(fn($val): string => strtolower($val), explode(' ', $post['email']));
  $thread_bumped = false;
  if ($post['parent_id'] !== 0 && !in_array('sage', $email_split)) {
    $thread_replies_n = count_posts('NULL', $post['board_id'], $post['parent_id'], false, false);
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

  // handle noko
  $redirect_url = '/' . $board_cfg['id'] . '/';
  if (in_array('noko', $email_split) || $board_cfg['alwaysnoko']) {
    $redirect_url .= ($post['parent_id'] === 0 ? $inserted_post_id : $post['parent_id']) . '/#' . $post['board_id'] . '-' . $inserted_post_id;
  }

  $response = $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus(200);
  $response->getBody()->write(json_encode([
    'redirect_url' => $redirect_url,
  ]));
  return $response;
}

/**
 * Handle exceptions accordingly, produce sensible error page as a response.
 */
$error_handler = function(
  Request $request,
  Throwable $exception,
  bool $display_error_details,
  bool $log_errors,
  bool $log_error_details,
  ?LoggerInterface $logger = null
) use ($app) {
  if ($logger != null) {
    $logger->error($exception->getMessage());
  }

  // Determine context
  $context = 'board';
  if (str_starts_with($request->getUri()->getPath(), '/manage/') && funcs_manage_is_logged_in()) {
    $context = 'manage';
  }

  // Handle 404 error page
  if ($exception instanceof Slim\Exception\HttpNotFoundException) {
    $response = $app->getResponseFactory()->createResponse(SC_NOT_FOUND);
    $renderer = new PhpRenderer('templates/', [
      'context' => $context,
      'error_type' => '404',
      'error_message' => 'Not Found',
      'error_image' => '/static/err_404/' . MB_ERROR_IMAGES[404][array_rand(MB_ERROR_IMAGES[404])]
    ]);
    return $renderer->render($response, 'error.phtml');
  }

  // Handle custom exceptions
  if ($exception instanceof AppException || $exception instanceof DbException) {
    $error_code = $exception->getCode();
    $error_image = null;
    if (array_key_exists($error_code, MB_ERROR_IMAGES)) {
      $error_image = '/static/err_' . $error_code . '/' . MB_ERROR_IMAGES[$error_code][array_rand(MB_ERROR_IMAGES[$error_code])];
    }
    $response = $app->getResponseFactory()->createResponse($error_code);
    $renderer = new PhpRenderer('templates/', [
      'context' => $context,
      'error_type' => $error_code === SC_NOT_FOUND ? '404' : 'Error',
      'error_message' => $exception->getMessage(),
      'error_image' => $error_image
    ]);
    return $renderer->render($response, 'error.phtml');
  }
  
  // Handle all other exceptions
  $response = $app->getResponseFactory()->createResponse(SC_INTERNAL_ERROR);
  $renderer = new PhpRenderer('templates/', [
    'context' => $context,
    'error_type' => 'Critical Error',
    'error_message' => $exception->getMessage()
  ]);
  return $renderer->render($response, 'error.phtml');
};

if (MB_ENV === 'dev') {
  $app->addErrorMiddleware(false, false, false)
    ->setDefaultErrorHandler($error_handler);
} else {
  $app->addErrorMiddleware(false, false, false)
    ->setDefaultErrorHandler($error_handler);
}

$app->run();
