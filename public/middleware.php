<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$session_middleware = function(Request $request, RequestHandler $handler) : Response {
  session_start();
  setcookie(session_name(), session_id(), 2147483647, '/');

  return $handler->handle($request);
};
