<?php

use PHPUnit\Framework\TestCase;
use minichan\core\Route;
use minichan\core\Router;

require_once __ROOT__ . '/core/router.php';

class RouterTest extends TestCase
{
	private Router $router;

	protected function setUp(): void
	{
		$this->router = new Router();
	}

	public function test_route_constructor(): void
	{
		$handler = function () {};
		$route = new Route('GET', '/test', $handler);

		$this->assertSame('GET', $route->method);
		$this->assertSame('/test', $route->uri);
		$this->assertSame($handler, $route->handler);
	}

	public function test_add_route_stores_route(): void
	{
		$this->router->add_route(HTTP_GET, '/test', function () {});

		$this->assertCount(1, $this->router->routes[HTTP_GET]);
	}

	public function test_add_route_invalid_method_throws(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('invalid http method');

		$this->router->add_route('INVALID', '/test', function () {});
	}

	public function test_add_route_all_http_methods(): void
	{
		foreach (HTTP_METHODS as $method) {
			$this->router->add_route($method, '/test', function () {});
		}

		$this->assertCount(1, $this->router->routes[HTTP_GET]);
		$this->assertCount(1, $this->router->routes[HTTP_POST]);
		$this->assertCount(1, $this->router->routes[HTTP_PUT]);
		$this->assertCount(1, $this->router->routes[HTTP_PATCH]);
		$this->assertCount(1, $this->router->routes[HTTP_DELETE]);
	}

	public function test_match_route_exact(): void
	{
		$called = false;
		$this->router->add_route(HTTP_GET, '/hello', function () use (&$called) {
			$called = true;
		});

		$this->router->match_route(HTTP_GET, '/hello');
		$this->assertTrue($called);
	}

	public function test_match_route_with_params(): void
	{
		$captured = [];
		$this->router->add_route(HTTP_GET, '/board/:id/thread/:tid', function ($params) use (&$captured) {
			$captured = $params;
		});

		$this->router->match_route(HTTP_GET, '/board/b/thread/123');
		$this->assertSame('b', $captured['id']);
		$this->assertSame('123', $captured['tid']);
	}

	public function test_match_route_no_routes_for_method_throws(): void
	{
		$this->router->add_route(HTTP_GET, '/test', function () {});

		$this->expectException(Exception::class);
		$this->expectExceptionCode(404);

		$this->router->match_route(HTTP_POST, '/test');
	}

	public function test_match_route_strips_trailing_slash(): void
	{
		$called = false;
		$this->router->add_route(HTTP_GET, '/test', function () use (&$called) {
			$called = true;
		});

		$this->router->match_route(HTTP_GET, '/test/');
		$this->assertTrue($called);
	}

	public function test_match_route_root_preserves_slash(): void
	{
		$called = false;
		$this->router->add_route(HTTP_GET, '/', function () use (&$called) {
			$called = true;
		});

		$this->router->match_route(HTTP_GET, '/');
		$this->assertTrue($called);
	}

	public function test_match_route_case_insensitive(): void
	{
		$called = false;
		$this->router->add_route(HTTP_GET, '/test', function () use (&$called) {
			$called = true;
		});

		$this->router->match_route(HTTP_GET, '/TEST');
		$this->assertTrue($called);
	}

	public function test_middleware_runs_before_handler(): void
	{
		$order = [];

		$this->router->add_middleware(function () use (&$order) {
			$order[] = 'middleware';
		});
		$this->router->add_route(HTTP_GET, '/test', function () use (&$order) {
			$order[] = 'handler';
		});

		$this->router->match_route(HTTP_GET, '/test');
		$this->assertSame(['middleware', 'handler'], $order);
	}

	public function test_multiple_middlewares_run_in_order(): void
	{
		$order = [];

		$this->router->add_middleware(function () use (&$order) {
			$order[] = 'first';
		});
		$this->router->add_middleware(function () use (&$order) {
			$order[] = 'second';
		});
		$this->router->add_route(HTTP_GET, '/test', function () use (&$order) {
			$order[] = 'handler';
		});

		$this->router->match_route(HTTP_GET, '/test');
		$this->assertSame(['first', 'second', 'handler'], $order);
	}

	public function test_middleware_receives_params(): void
	{
		$captured = [];

		$this->router->add_middleware(function ($params) use (&$captured) {
			$captured = $params;
		});
		$this->router->add_route(HTTP_GET, '/board/:id', function () {});

		$this->router->match_route(HTTP_GET, '/board/b');
		$this->assertSame('b', $captured['id']);
	}

	public function test_multiple_routes_matches_correct_one(): void
	{
		$matched = '';

		$this->router->add_route(HTTP_GET, '/foo', function () use (&$matched) {
			$matched = 'foo';
		});
		$this->router->add_route(HTTP_GET, '/bar', function () use (&$matched) {
			$matched = 'bar';
		});

		$this->router->match_route(HTTP_GET, '/bar');
		$this->assertSame('bar', $matched);
	}
}
