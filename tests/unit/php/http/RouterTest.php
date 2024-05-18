<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Miniboard\http\RequestContext;
use Miniboard\http\Route;
use Miniboard\http\Router;

require __DIR__ . '/../../../../public/php/http/router.php';

final class RouterTest extends TestCase {
  public function test_match_route_get_simple_match() {
    $handler = fn(RequestContext $context) => true;
    $router = new Router();
    $router->add_route(new Route(HTTP_GET, '/test/route', $handler));

    $result = $router->match_route(HTTP_GET, '/test/route');

    $this->assertNotNull($result);
    $this->assertEquals($result->method, HTTP_GET);
    $this->assertEquals($result->uri_match, '/test/route');
    $this->assertEquals($result->uri_vars, []);
  }

  public function test_match_route_get_simple_notfound() {
    $handler = fn(RequestContext $context) => true;
    $router = new Router();
    $router->add_route(new Route(HTTP_GET, '/test/route', $handler));

    $result = $router->match_route(HTTP_GET, '/test/notfound');

    $this->assertNull($result);
  }

  public function test_match_route_get_vars_match() {
    $handler = fn(RequestContext $context) => true;
    $router = new Router();
    $router->add_route(new Route(HTTP_GET, '/board/:board_id/thread/:thread_id', $handler));

    $result = $router->match_route(HTTP_GET, '/board/1234/thread/4321');

    $this->assertNotNull($result);
    $this->assertEquals($result->method, HTTP_GET);
    $this->assertEquals($result->uri_match, '/board/1234/thread/4321');
    $this->assertEquals($result->uri_vars, [':board_id' => '1234', ':thread_id' => '4321']);
  }
}
