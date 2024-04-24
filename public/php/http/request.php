<?php

namespace Miniboard\http;

enum RequestMethod: string {
  case GET = 'GET';
  case POST = 'POST';
  case PUT = 'PUT';
  case PATCH = 'PATCH';
  case DELETE = 'DELETE';
}

class RequestContext {
  public RequestMethod $method;
  public string $uri_match;
  public array $uri_vars;

  public function __construct(RequestMethod $method, string $uri_match) {
    $this->method = $method;
    $this->uri_match = $uri_match;
    $this->uri_vars = [];
  }
}
