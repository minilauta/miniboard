<?php

namespace Miniboard\http;

require __DIR__ . '/request.php';

class Route {
  public string $method;
  public string $uri;
  public $handler;
  private array $uri_vars;
  private string $uri_regex;

  public function __construct(string $method, string $uri, callable $handler) {
    $this->method = $method;
    $this->uri = str_replace('/', '\/', $uri);
    $this->handler = $handler;
    $this->uri_vars = $this->parse_uri_vars();
    $this->uri_regex = $this->build_uri_regex();
  }

  private function parse_uri_vars(): array {
    $matches = [];
    if (preg_match_all('/:[A-Za-z0-9_-]{1,32}/i', $this->uri, $matches) > 0) {
      return $matches[0];
    }

    return [];
  }

  private function build_uri_regex(): string {
    $uri_regex = preg_replace('/:[A-Za-z0-9_-]{1,32}/i', '([A-Za-z0-9_-]{1,16})', $this->uri);
    if ($uri_regex == null) {
      throw new \Exception('Route::build_match_uri error', -1);
    }

    return "/{$uri_regex}/i";
  }

  public function match(string $req_uri): ?RequestContext {
    $matches = [];

    if (preg_match($this->uri_regex, $req_uri, $matches) > 0) {
      $matched_uri = $matches[0];
      $matched_vars = array_slice($matches, 1, count($this->uri_vars));

      $req_context = new RequestContext($this->method, $matched_uri);
      foreach ($this->uri_vars as $idx => &$val) {
        $req_context->uri_vars[$val] = $matched_vars[$idx];
      }
      
      call_user_func($this->handler, $req_context);

      return $req_context;
    }

    return null;
  }
}

class Router {
  private array $routes;

  public function __construct() {
    $this->routes = [
      HTTP_GET => [],
      HTTP_POST => [],
      HTTP_PUT => [],
      HTTP_PATCH => [],
      HTTP_DELETE => [],
    ];
  }

  public function add_route(Route $route): void {
    if (!isset($this->routes[$route->method])) {
      return;
    }

    array_push($this->routes[$route->method], $route);
  }

  public function match_route(string $req_method, string $req_uri): ?RequestContext {
    if (!isset($this->routes[$req_method])) {
      return null;
    } else if (empty($this->routes[$req_method])) {
      return null;
    }

    foreach ($this->routes[$req_method] as $idx => &$route) {
      $req_context = $route->match($req_uri);
      if ($req_context != null) {
        return $req_context;
      }
    }

    return null;
  }
}
