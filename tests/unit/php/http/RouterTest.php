<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Miniboard\http\RouteMatch;
use Miniboard\http\Router;

require __ROOT__ . '/public/php/http/router.php';

final class RouterTest extends TestCase {
  public function test_match_route_get_simple_match() {
    $handler = fn(RouteMatch $route_match) => true;
    $router = new Router();
    $router->add_route(HTTP_GET, '/test/route', $handler);

    $result = $router->match_route(HTTP_GET, '/test/route');

    $this->assertNotNull($result);
    $this->assertEquals($result->uri_vars, []);
    $this->assertNotNull($result->route_handler);
  }

  public function test_match_route_get_simple_notfound() {
    $handler = fn(RouteMatch $route_match) => true;
    $router = new Router();
    $router->add_route(HTTP_GET, '/test/route', $handler);

    $result = $router->match_route(HTTP_GET, '/test/not/found');

    $this->assertNull($result);
  }

  public function test_match_route_get_simple_match_uri_vars() {
    $handler = fn(RouteMatch $route_match) => true;
    $router = new Router();
    $router->add_route(HTTP_GET, '/board/:board_id/thread/:thread_id', $handler);

    $result = $router->match_route(HTTP_GET, '/board/1234/thread/4321');

    $this->assertNotNull($result);
    $this->assertEquals($result->uri_vars, [':board_id' => '1234', ':thread_id' => '4321']);
    $this->assertNotNull($result->route_handler);
  }

  public function test_match_route_get_complex_match_uri_vars() {
    $handler = fn(RouteMatch $route_match) => true;
    $router = new Router();
    $router->add_route(HTTP_GET, '/admin/:board_id/thread/:thread_id', $handler);
    $router->add_route(HTTP_GET, '/report/:board_id/thread/:thread_id/:post_id', $handler);
    $router->add_route(HTTP_GET, '/board/:board_id/thread/:thread_id', $handler);

    $result = $router->match_route(HTTP_GET, '/report/b/thread/1337/1');

    $this->assertNotNull($result);
    $this->assertEquals($result->uri_vars, [':board_id' => 'b', ':thread_id' => '1337', ':post_id' => '1']);
    $this->assertNotNull($result->route_handler);
  }
}
