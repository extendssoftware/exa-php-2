<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimpleRouterTest extends TestCase
{
    public function testExtractsEncodedParametersWithoutCallingHandler(): void
    {
        $route = new Route('route.1', Method::Get, '/articles/{id}/tags/{tag}', 'handler');
        $router = new SimpleRouter(new RouteCollection([$route]));
        $match = $router->match(new Request(Method::Get, new Uri('/articles/abc/tags/a%2Fb?sort=asc')));
        $this->assertSame($route, $match->route);
        $this->assertSame(['id' => 'abc', 'tag' => 'a%2Fb'], $match->parameters);
        $this->assertSame('abc', $match->parameter('id'));
    }

    public function testStaticPathReservesItsMethodsRegardlessOfRegistrationOrder(): void
    {
        $dynamic = new Route('route.2', Method::Get, '/articles/{id}', 'handler');
        $static = new Route('route.3', Method::Post, '/articles/new', 'handler');
        foreach ([[$dynamic, $static], [$static, $dynamic]] as $routes) {
            $collection = new RouteCollection($routes);
            $router = new SimpleRouter($collection);
            $this->assertSame($routes, $collection->all());
            $request = new Request(Method::Get, new Uri('/articles/new'));
            $this->assertNull($router->match($request));
            $this->assertSame([Method::Post], $router->allowedMethods($request));
            $this->assertSame($static, $router->match($request->withMethod(Method::Post))->route);
        }
    }

    public function testEquivalentPatternsAcrossMethodsUseTheirOwnParameterNames(): void
    {
        $router = new SimpleRouter(new RouteCollection([
            new Route('route.4', Method::Get, '/articles/{id}', 'handler'),
            new Route('route.5', Method::Delete, '/articles/{article}', 'handler'),
        ]));
        $request = new Request(Method::Delete, new Uri('/articles/42'));
        $this->assertSame(['article' => '42'], $router->match($request)->parameters);
        $this->assertSame([Method::Get, Method::Delete], $router->allowedMethods($request));
    }

    public function testTiedOverlappingPatternsUseRegistrationOrder(): void
    {
        $first = new Route('route.6', Method::Get, '/a/{value}', 'handler');
        $second = new Route('route.7', Method::Get, '/{value}/b', 'handler');
        $request = new Request(Method::Get, new Uri('/a/b'));
        $this->assertSame($first, new SimpleRouter(new RouteCollection([$first, $second]))->match($request)->route);
        $this->assertSame($second, new SimpleRouter(new RouteCollection([$second, $first]))->match($request)->route);
    }

    #[DataProvider('paths')]
    public function testExactPathMatching(string $pattern, string $uri, bool $matches): void
    {
        $router = new SimpleRouter(new RouteCollection([new Route('route.8', Method::Get, $pattern, 'handler')]));
        $this->assertSame($matches, $router->match(new Request(Method::Get, new Uri($uri))) !== null);
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function paths(): iterable
    {
        yield ['/articles', '/articles', true];
        yield ['/articles', '/articles/', false];
        yield ['/articles/', '/articles/', true];
        yield ['/articles/{id}', '/articles/', false];
        yield ['/articles/{id}', '/articles/a/b', false];
        yield ['/articles/{id}', '/articles/0', true];
        yield ['/articles/{id}', '/Articles/1', false];
        yield ['/a.b', '/axb', false];
        yield ['/a.b', '/a.b', true];
        yield ['/a%2Fb', '/a%2fb', false];
        yield ['/a//b', '/a//b', true];
        yield ['/a/../b', '/b', false];
        yield ['/', 'https://example.test', true];
    }

    public function testHeadAndOptionsRequireExplicitRegistration(): void
    {
        $get = new Route('route.9', Method::Get, '/', 'handler');
        $router = new SimpleRouter(new RouteCollection([$get]));
        foreach ([Method::Head, Method::Options] as $method) {
            $request = new Request($method, new Uri('/'));
            $this->assertNull($router->match($request));
            $this->assertSame([Method::Get], $router->allowedMethods($request));
        }
        $head = new Route('route.10', Method::Head, '/', 'handler');
        $options = new Route('route.11', Method::Options, '*', 'handler');
        $router = new SimpleRouter(new RouteCollection([$get, $head, $options]));
        $this->assertSame($head, $router->match(new Request(Method::Head, new Uri('/')))->route);
        $this->assertSame($options, $router->match(new Request(Method::Options, new Uri('*')))->route);
    }

    public function testUnmatchedAndConnectTargetsHaveNoAllowedMethods(): void
    {
        $router = new SimpleRouter(new RouteCollection([new Route('route.12', Method::Get, '/', 'handler')]));
        foreach ([new Request(Method::Get, new Uri('/missing')), new Request(Method::Connect, new Uri('//host:443'))] as $r) {
            $this->assertNull($router->match($r));
            $this->assertSame([], $router->allowedMethods($r));
        }
    }

}
