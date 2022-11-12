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
  $post = select_post($args['board_id'], $args['post_id']);
  if ($post == null) {
    $response->getBody()->write('Error: INVALID_POST: ' . $args['board_id'] . '/' . $args['post_id']);
    $response = $response->withStatus(400);
    return $response;
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

  // validate post
  $validated_post = validate_request($args);
  if (isset($validated_post['error'])) {
    $response->getBody()->write('Error: ' . $validated_post['error']);
    $response = $response->withStatus(500);
    return $response;
  }

  // toggle hide
  $hide = select_hide(session_id(), $args['board_id'], $args['post_id']);
  if ($hide == null) {
    $hide = create_hide($args, $params);
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

  // validate post
  $validated_post = validate_post_reportform($args, $params);
  if (isset($validated_post['error'])) {
    $response->getBody()->write('Post validation error: ' . $validated_post['error']);
    $response = $response->withStatus(400);
    return $response;
  }

  // get post
  $post = select_post($args['board_id'], $args['post_id']);
  if ($post == null) {
    $response->getBody()->write('Error: INVALID_POST: ' . $args['board_id'] . '/' . $args['post_id']);
    $response = $response->withStatus(400);
    return $response;
  }

  // create report
  $created_report = create_report($args, $params, $post);

  // insert report
  $inserted_report_id = insert_report($created_report);

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
  $query_page = get_query_param_int($query_params, 'page', 0, 0, 1000);

  // get threads
  $threads = select_posts(session_id(), $args['board_id'], 0, true, $board_threads_per_page * $query_page, $board_threads_per_page);

  // get replies
  foreach ($threads as $key => $thread) {
    $threads[$key]['replies'] = select_posts_preview($args['board_id'], $thread['id'], 0, $board_posts_per_preview);
  }

  // get thread count
  $threads_n = count_posts($args['board_id'], 0);

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
  $query_page = get_query_param_int($query_params, 'page', 0, 0, 1000);

  // get threads
  $threads = select_posts(session_id(), $args['board_id'], 0, true, $board_threads_per_catalog_page * $query_page, $board_threads_per_catalog_page);

  // get thread reply counts
  foreach ($threads as $key => $thread) {
    /** @var int */
    $reply_count = count_posts(board_id: $args['board_id'], parent_id: $thread['id']);
    if (is_int($reply_count)) {
      $threads[$key]['reply_count'] = $reply_count;
    }
  }

  // get thread count
  $threads_n = count_posts($args['board_id'], 0);

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
  $thread = select_post($args['board_id'], $args['thread_id']);

  // get replies
  $replies = select_posts(session_id(), $args['board_id'], $args['thread_id'], false, 0, 1000);

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

  // validate post
  $validated_post = validate_post_postform($args, $params);
  if (isset($validated_post['error'])) {
    $response->getBody()->write('Post validation error: ' . $validated_post['error']);
    $response = $response->withStatus(400);
    return $response;
  }

  // validate file
  $validated_file = validate_file($file, $board_cfg);
  if (isset($validated_file['error'])) {
    $response->getBody()->write('File validation error: ' . $validated_file['error']);
    $response = $response->withStatus(400);
    return $response;
  }

  // check md5 file collisions
  $file_collisions = [];
  if (!isset($validated_file['no_file'])) {
    $file_collisions = select_files_by_md5($validated_file['file_md5']);
  }

  // upload file
  $uploaded_file = upload_file($file, $validated_file, $file_collisions, $board_cfg);
  if (isset($uploaded_file['error'])) {
    $response->getBody()->write('File upload error: ' . $uploaded_file['error']);
    $response = $response->withStatus(500);
    return $response;
  }

  // create post
  $created_post = create_post($args, $params, $uploaded_file);

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
    return $response;
  }

  throw $exception;
};

$app->addErrorMiddleware(true, true, true)
  ->setDefaultErrorHandler($error_handler);

$app->run();
