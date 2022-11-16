<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funcs_common.php';
require_once __DIR__ . '/funcs_file.php';
require_once __DIR__ . '/funcs_post.php';
require_once __DIR__ . '/funcs_report.php';
require_once __DIR__ . '/funcs_hide.php';
require_once __DIR__ . '/functions.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->add(new Middlewares\TrailingSlash(true));
$app->add($session_middleware);

$app->get('/', function (Request $request, Response $response, array $args) {
  $response = $response
    ->withHeader('Location', '/' . MB_BOARDS[array_key_first(MB_BOARDS)]['id'] . '/')
    ->withStatus(303);
  return $response;
});

$app->get('/manage/', function (Request $request, Response $response, array $args) {
  $response->getBody()->write('Management not implemented yet');
  $response = $response->withStatus(200);
  return $response;
});

$app->get('/{board_id}/{post_id}/report/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // get post
  $post = select_post($board_cfg['id'], $args['post_id']);
  if ($post == null) {
    throw new ApiException("post with ID /{$board_cfg['id']}/{$args['post_id']} not found", SC_BAD_REQUEST);
  }

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'post' => $post
  ]);
  return $renderer->render($response, 'report.phtml');
});

$app->post('/{board_id}/{post_id}/hide/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // parse request body
  $params = (array) $request->getParsedBody();

  // toggle hide
  $hide = select_hide(session_id(), $board_cfg['id'], $args['post_id']);
  if ($hide == null) {
    $hide = funcs_hide_create(session_id(), $board_cfg['id'], $args['post_id']);
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
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // parse request body
  $params = (array) $request->getParsedBody();

  // validate request fields
  funcs_report_validate_fields($params, MB_GLOBAL['report_types']);

  // get post
  $post = select_post($board_cfg['id'], $args['post_id']);
  if ($post == null) {
    throw new ApiException("post with ID /{$board_cfg['id']}/{$args['post_id']} not found", SC_BAD_REQUEST);
  }

  // create report
  $ip = funcs_common_get_client_remote_address(MB_GLOBAL['cloudflare'], $_SERVER);
  $report = funcs_report_create($ip, $board_cfg['id'], $post['id'], $params['type'], MB_GLOBAL['report_types']);

  // insert report
  $inserted_report_id = insert_report($report);

  $response->getBody()->write('Post reported');
  $response = $response->withStatus(200);
  return $response;
}

$app->get('/{board_id}/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);
  $board_threads_per_page = $board_cfg['threads_per_page'];
  $board_posts_per_preview = $board_cfg['posts_per_preview'];

  // get query params
  $query_params = $request->getQueryParams();
  $query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

  // get threads
  $threads = select_posts(session_id(), $board_cfg['id'], 0, true, $board_threads_per_page * $query_page, $board_threads_per_page);

  // get replies
  foreach ($threads as $key => $thread) {
    $threads[$key]['replies'] = select_posts_preview(session_id(), $thread['board_id'], $thread['id'], 0, $board_posts_per_preview);
  }

  // get thread count
  $threads_n = count_posts(session_id(), $board_cfg['id'], 0);

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'threads' => $threads,
    'page' => $query_page,
    'page_n' => ceil($threads_n / $board_threads_per_page)
  ]);
  return $renderer->render($response, 'board.phtml');
});

$app->get('/{board_id}/catalog/', function (Request $request, Response $response, array $args) {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);
  $board_threads_per_catalog_page = $board_cfg['threads_per_catalog_page'];

  // get query params
  $query_params = $request->getQueryParams();
  $query_page = funcs_common_parse_input_int($query_params, 'page', 0, 0, 1000);

  // get threads
  $threads = select_posts(session_id(), $board_cfg['id'], 0, true, $board_threads_per_catalog_page * $query_page, $board_threads_per_catalog_page);

  // get thread reply counts
  foreach ($threads as $key => $thread) {
    $reply_count = count_posts(session_id(), $thread['board_id'], $thread['id']);
    if (is_int($reply_count)) {
      $threads[$key]['reply_count'] = $reply_count;
    }
  }

  // get thread count
  $threads_n = count_posts(session_id(), $board_cfg['id'], 0);

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

  // get thread
  $thread = select_post($board_cfg['id'], $args['thread_id']);
  if ($thread['parent_id'] !== 0) {
    throw new ApiException('not a valid thread', SC_BAD_REQUEST);
  }

  // get replies
  $replies = select_posts(session_id(), $thread['board_id'], $thread['id'], false, 0, 1000);

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'thread' => $thread,
    'replies' => $replies
  ]);
  return $renderer->render($response, 'thread.phtml');
});

$app->post('/{board_id}/', function (Request $request, Response $response, array $args) {
  return handle_postform($request, $response, $args);
});

$app->post('/{board_id}/{thread_id}/', function (Request $request, Response $response, array $args) {
  return handle_postform($request, $response, $args);
});

function handle_postform(Request $request, Response $response, array $args): Response {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($args['board_id']);

  // parse request body
  $params = (array) $request->getParsedBody();
  $file = $request->getUploadedFiles()['file'];

  // validate request fields
  funcs_common_validate_fields($params, $board_cfg['fields_post']);

  // validate request file
  $file_info = funcs_file_validate_upload($file, true, $board_cfg['mime_ext_types'], $board_cfg['maxkb'] * 1000);

  // check md5 file collisions
  $file_collisions = [];
  if ($file_info != null) {
    $file_collisions = select_files_by_md5($file_info['md5']);
  }

  // upload file
  $file_upload = funcs_file_execute_upload($file, $file_info, $file_collisions, $board_cfg['max_width'], $board_cfg['max_height']);

  // create post
  $created_post = create_post($args, $params, $file_upload);

  // insert post
  $inserted_post_id = insert_post($created_post);

  // bump thread
  $bumped_thread = bump_thread($created_post['board_id'], $created_post['parent_id']);

  // handle noko
  $location_header = '/' . $board_cfg['id'] . '/';
  if (strtolower($created_post['email']) === 'noko' || $board_cfg['alwaysnoko']) {
    $location_header .= ($created_post['parent_id'] === 0 ? $inserted_post_id : $created_post['parent_id']) . '/';
  }

  $response = $response
    ->withHeader('Location', $location_header)
    ->withStatus(303);
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

  if ($exception instanceof ApiException || $exception instanceof FuncException || $exception instanceof DbException) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write($exception->getMessage());
    return $response
      ->withStatus($exception->getCode());
  }

  throw $exception;
};

$app->addErrorMiddleware(true, true, true)
  ->setDefaultErrorHandler($error_handler);

$app->run();
