<?php

namespace minichan\core;

use Closure;
use Exception;

define('HTTP_GET', 'GET');
define('HTTP_POST', 'POST');
define('HTTP_PUT', 'PUT');
define('HTTP_PATCH', 'PATCH');
define('HTTP_DELETE', 'DELETE');
define('HTTP_METHODS', [HTTP_GET, HTTP_POST, HTTP_PUT, HTTP_PATCH, HTTP_DELETE]);

class Route
{
	public string $method;
	public string $uri;
	public $handler;

	public function __construct(string $method, string $uri, Closure $handler)
	{
		$this->method = $method;
		$this->uri = $uri;
		$this->handler = $handler;
	}
}

class Router
{
	/**
	 * Summary of middlewares
	 * @var callable
	 */
	private array $middlewares;
	/**
	 * Summary of routes
	 * @var array
	 */
	public array $routes;

	public function __construct()
	{
		$this->middlewares = [];
		$this->routes = [];
	}

	public function add_middleware(Closure $handler): void
	{
		$this->middlewares[] = $handler;
	}

	public function add_route(string $method, string $uri, Closure $handler): void
	{
		if (!in_array($method, HTTP_METHODS)) {
			throw new Exception('invalid http method');
		}

		if (!isset($this->routes[$method])) {
			$this->routes[$method] = [];
		}

		$uri_pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $uri);
		$this->routes[$method][$uri_pattern] = new Route($method, $uri, $handler);
	}

	public function match_route(string $method, string $uri): void
	{
		if (!isset($this->routes[$method])) {
			throw new Exception('not found', 404);
		}

		$uri = strlen($uri) > 1 ? rtrim($uri, '/') : $uri;
		$uri = strtolower($uri);

		foreach ($this->routes[$method] as $uri_pattern => $route) {
			$matches = [];
			if (preg_match("#^{$uri_pattern}$#", $uri, $matches)) {
				$params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

				foreach ($this->middlewares as &$middleware) {
					call_user_func($middleware, $params);
				}
				call_user_func($route->handler, $params);

				return;
			}
		}
	}
}
