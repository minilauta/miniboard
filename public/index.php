<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/database.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->get('/{board_id}/', function (Request $request, Response $response, array $args) {
  // get threads
  $threads = select_posts($args['board_id'], 0, true, 0, 10);
  
  // get replies
  foreach ($threads as $key => $thread) {
    $threads[$key]['replies'] = select_posts($args['board_id'], $thread['id'], false, 0, 4);
  }

  $renderer = new PhpRenderer('templates/', [
    'board_id' => $args['board_id'],
    'board_name' => 'Satunnainen',
    'board_desc' => 'Jotain satunnaista paskaa',
    'threads' => $threads
  ]);
  return $renderer->render($response, 'board.phtml');
});

$app->get('/{board_id}/{thread_id}', function (Request $request, Response $response, array $args) {
  // get thread
  $thread = select_post($args['board_id'], $args['thread_id']);
  // get replies
  $replies = select_posts($args['board_id'], $args['thread_id'], false, 0, 1000);

  $renderer = new PhpRenderer('templates/', [
    'board_id' => $args['board_id'],
    'board_name' => 'Satunnainen',
    'board_desc' => 'Jotain satunnaista paskaa',
    'thread' => $thread,
    'replies' => $replies
  ]);
  return $renderer->render($response, 'thread.phtml');
});

$app->post('/{board_id}/', function(Request $request, Response $response, array $args) {
  // parse request body
  $params = (array) $request->getParsedBody();
  $file = $request->getUploadedFiles()['file'];

  // upload file
  $uploaded_file = upload_file($file);

  // create post
  $created_post = create_post($args, $params, $uploaded_file);

  // insert post
  $inserted_post_id = insert_post($created_post);

  $response->getBody()->write('form keys: ' . implode(',', array_keys($params)) . '<br>');
  $response->getBody()->write('file keys: ' . implode(',', array_keys($uploaded_file)) . '<br>');
  $response->getBody()->write('post keys: ' . implode(',', array_keys($created_post)) . '<br>');
  $response->getBody()->write('inserted post: ' . $inserted_post_id);
  return $response;
});

$app->post('/{board_id}/{thread_id}', function(Request $request, Response $response, array $args) {
  // parse request body
  $params = (array) $request->getParsedBody();
  $file = $request->getUploadedFiles()['file'];

  // upload file
  $uploaded_file = upload_file($file);

  // create post
  $created_post = create_post($args, $params, $uploaded_file);

  // insert post
  $inserted_post_id = insert_post($created_post);

  $response->getBody()->write('form keys: ' . implode(',', array_keys($params)) . '<br>');
  $response->getBody()->write('file keys: ' . implode(',', array_keys($uploaded_file)) . '<br>');
  $response->getBody()->write('post keys: ' . implode(',', array_keys($created_post)) . '<br>');
  $response->getBody()->write('inserted post: ' . $inserted_post_id);
  return $response;
});

$app->run();
