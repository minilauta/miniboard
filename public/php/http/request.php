<?php

namespace Miniboard\http;

define('HTTP_GET', 'GET');
define('HTTP_POST', 'POST');
define('HTTP_PUT', 'PUT');
define('HTTP_PATCH', 'PATCH');
define('HTTP_DELETE', 'DELETE');

class RequestContext {
  public string $method;
  public string $uri_match;
  public array $uri_vars;

  public function __construct(string $method, string $uri_match) {
    $this->method = $method;
    $this->uri_match = $uri_match;
    $this->uri_vars = [];
  }
}
