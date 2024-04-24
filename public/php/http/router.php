<?php

namespace Miniboard\http;

require __DIR__ . '/request.php';

class Route {
  public string $uri;
  public RequestMethod $method;
  public $handler;
  private array $uri_vars;
  private string $uri_regex;

  public function __construct(string $uri, RequestMethod $method, callable $handler) {
    $this->uri = $uri;
    $this->method = $method;
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

  public function match(string $req_uri): bool {
    $matches = [];
    if (preg_match($this->uri_regex, $req_uri, $matches) > 0) {
      $matched_uri = $matches[0];
      $matched_vars = array_slice($matches, 1, count($this->uri_vars));

      $req_context = new RequestContext($this->method, $matched_uri);
      foreach ($this->uri_vars as $idx => &$val) {
        $req_context->uri_vars[$val] = $matched_vars[$idx];
      }
      
      call_user_func($this->handler, $req_context);

      return true;
    }

    return false;
  }
}

class Router {
  private array $routes;

  public function __construct() {
    $this->routes = [];
  }

  public function add_route(Route $route): void {
    array_push($this->routes, $route);
  }
}
