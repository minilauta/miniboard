<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->add(new Middlewares\TrailingSlash(true));

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

$app->get('/{board_id}/', function (Request $request, Response $response, array $args) {
  // validate get
  $validated_get = validate_get($args);
  if (isset($validated_get['error'])) {
    $response->getBody()->write('Error: ' . $validated_get['error']);
    $response = $response->withStatus(500);
    return $response;
  }

  // get query params
  $query_params = $request->getQueryParams();
  $query_page = get_query_param_int($query_params, 'page', 0, 0, 1000);

  // get board config
  $board_cfg = $validated_get['board_cfg'];
  $board_threads_per_page = $board_cfg['threads_per_page'];
  $board_posts_per_preview = $board_cfg['posts_per_preview'];

  // get threads
  $threads = select_posts($args['board_id'], 0, true, $board_threads_per_page * $query_page, $board_threads_per_page);
  
  // get replies
  foreach ($threads as $key => $thread) {
    $threads[$key]['replies'] = select_posts_preview($args['board_id'], $thread['id'], 0, $board_posts_per_preview);
  }

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'threads' => $threads
  ]);
  return $renderer->render($response, 'board.phtml');
});

$app->get('/{board_id}/{thread_id}/', function (Request $request, Response $response, array $args) {
  // validate get
  $validated_get = validate_get($args);
  if (isset($validated_get['error'])) {
    $response->getBody()->write('Error: ' . $validated_get['error']);
    $response = $response->withStatus(500);
    return $response;
  }

  // get board config
  $board_cfg = $validated_get['board_cfg'];

  // get thread
  $thread = select_post($args['board_id'], $args['thread_id']);
  
  // get replies
  $replies = select_posts($args['board_id'], $args['thread_id'], false, 0, 1000);

  $renderer = new PhpRenderer('templates/', [
    'board' => $board_cfg,
    'thread' => $thread,
    'replies' => $replies
  ]);
  return $renderer->render($response, 'thread.phtml');
});

$app->post('/{board_id}/', function(Request $request, Response $response, array $args) {
  return handle_postform($request, $response, $args);
});

$app->post('/{board_id}/{thread_id}/', function(Request $request, Response $response, array $args) {
  return handle_postform($request, $response, $args);
});

function handle_postform(Request $request, Response $response, array $args) : Response {
  // parse request body
  $params = (array) $request->getParsedBody();
  $file = $request->getUploadedFiles()['file'];

  // validate post
  $validated_post = validate_post($args, $params);
  if (isset($validated_post['error'])) {
    $response->getBody()->write('Post validation error: ' . $validated_post['error']);
    $response = $response->withStatus(400);
    return $response;
  }

  // get board config
  $board_cfg = $validated_post['board_cfg'];
  
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
  $bumped_thread = bump_thread($created_post['board'], $created_post['parent']);

  // handle noko
  $location_header = '/' . $board_cfg['id'] . '/';
  if (strtolower($created_post['email']) === 'noko') {
    $location_header .= ($created_post['parent'] === 0 ? $inserted_post_id : $created_post['parent']) . '/';
  }

  $response = $response
    ->withHeader('Location', $location_header)
    ->withStatus(303);
  return $response;
}

$app->run();
