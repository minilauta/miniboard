<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$session_middleware = function(Request $request, RequestHandler $handler) : Response {
  session_set_cookie_params([
    'lifetime' => 315360000,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => false,
    'samesite' => 'Strict'
  ]);
  session_start();

  return $handler->handle($request);
};
