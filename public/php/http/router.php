<?php

namespace Miniboard\http;

require __DIR__ . '/request.php';

class RouteMatch {
  public array $uri_vars;
  public $route_handler;

  public function __construct(array $uri_vars, callable $route_handler) {
    $this->uri_vars = $uri_vars;
    $this->route_handler = $route_handler;
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

  public function add_route(string $method, string $uri, callable $handler): void {
    $uri_split = array_filter(explode('/', $uri));
    $route_part = &$this->routes[$method];
    foreach ($uri_split as $idx => &$val) {
      if (!isset($route_part[$val])) {
        $route_part[$val] = [];
      }
      if ($idx === array_key_last($uri_split)) {
        $route_part[$val]['!HANDLER'] = $handler;
      }
      $route_part = &$route_part[$val];
    }
  }

  public function match_route(string $method, string $uri): ?RouteMatch {
    if (!isset($this->routes[$method])) {
      return null;
    }

    $uri_split = array_filter(explode('/', $uri));
    $route_part = &$this->routes[$method];
    $uri_vars = [];
    $route_handler = null;
    foreach ($uri_split as &$val) {
      if (isset($route_part[$val])) {
        $route_part = &$route_part[$val];
      } else if (!empty($route_part)) {
        foreach ($route_part as $rp_key => &$rp_val) {
          if ($rp_key[0] === ':') {
            $uri_vars[$rp_key] = $val;
            $route_part = $rp_val;
          }
          break;
        }
      }
    }

    if (!empty($route_part) && isset($route_part['!HANDLER'])) {
      $route_handler = &$route_part['!HANDLER'];
    } else {
      return null;
    }

    return new RouteMatch($uri_vars, $route_handler);
  }
}
