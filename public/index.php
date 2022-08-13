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

$app->get('/{board_id}', function (Request $request, Response $response, array $args) {
  // construct temp posts
  $posts = array();
  for ($i = 0; $i < 10; $i++) {
    $post_datetime = new DateTime();
    $post = [
      'id' => random_int(1, 10000),
      'parent' => NULL,
      'replies' => array(),
      'name' => 'Anonymous',
      'datetime' => $post_datetime->format('d/m/Y H:i:s'),
      'file' => [
        'path' => 'src/123456789.png',
        'name' => '123456789.png',
        'size' => 123,
        'width' => 128,
        'height' => 256,
        'name_original' => 'test.png'
      ],
      'message' => substr(md5(mt_rand()), 7)
    ];
    if (random_int(1, 100) > 70) {
      for ($j = 0; $j < 4; $j++) {
        $post['replies'][] = [
          'id' => random_int(1, 10000),
          'parent' => $post['id'],
          'replies' => NULL,
          'name' => 'Anonymous',
          'datetime' => $post_datetime->format('d/m/Y H:i:s'),
          'file' => [
            'path' => 'src/123456789.png',
            'name' => '123456789.png',
            'size' => 123,
            'width' => 128,
            'height' => 256,
            'name_original' => 'test.png'
          ],
          'message' => substr(md5(mt_rand()), 7)
        ];
      }
    }
    $posts[] = $post;
  }
  $renderer = new PhpRenderer('templates/', [
    'board_id' => $args['board_id'],
    'board_name' => 'Satunnainen',
    'board_desc' => 'Jotain satunnaista paskaa',
    'posts' => $posts]
  );
  return $renderer->render($response, 'board.phtml');
});

// create new post
$app->post('/{board_id}', function(Request $request, Response $response, array $args) {
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
