<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

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
$app->post('/', function(Request $request, Response $response, array $args) {
  // parse request body
  $params = (array) $request->getParsedBody();
  $files = $request->getUploadedFiles();

  $response->getBody()->write(implode(',', array_keys($params)));
  $response->getBody()->write('<br>');
  $response->getBody()->write(implode(',', array_keys($files)));
  return $response;
});

$app->run();
