<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Routing\Exception\DuplicateRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SimpleRouterTest extends TestCase
{
    public function testExtractsEncodedParametersWithoutCallingHandler(): void
    {
        $route = new Route(Method::Get, '/articles/{id}/tags/{tag}', 'handler');
        $router = new SimpleRouter([$route]);
        $match = $router->match(new Request(Method::Get, new Uri('/articles/abc/tags/a%2Fb?sort=asc')));
        $this->assertSame($route, $match->route);
        $this->assertSame(['id' => 'abc', 'tag' => 'a%2Fb'], $match->parameters);
        $this->assertSame('abc', $match->parameter('id'));
    }

    public function testStaticPathReservesItsMethodsRegardlessOfRegistrationOrder(): void
    {
        $dynamic = new Route(Method::Get, '/articles/{id}', 'handler');
        $static = new Route(Method::Post, '/articles/new', 'handler');
        foreach ([[$dynamic, $static], [$static, $dynamic]] as $routes) {
            $router = new SimpleRouter($routes);
            $request = new Request(Method::Get, new Uri('/articles/new'));
            $this->assertNull($router->match($request));
            $this->assertSame([Method::Post], $router->allowedMethods($request));
            $this->assertSame($static, $router->match($request->withMethod(Method::Post))->route);
        }
    }

    public function testEquivalentPatternsAcrossMethodsUseTheirOwnParameterNames(): void
    {
        $router = new SimpleRouter([
            new Route(Method::Get, '/articles/{id}', 'handler'),
            new Route(Method::Delete, '/articles/{article}', 'handler'),
        ]);
        $request = new Request(Method::Delete, new Uri('/articles/42'));
        $this->assertSame(['article' => '42'], $router->match($request)->parameters);
        $this->assertSame([Method::Get, Method::Delete], $router->allowedMethods($request));
    }

    public function testTiedOverlappingPatternsUseRegistrationOrder(): void
    {
        $first = new Route(Method::Get, '/a/{value}', 'handler');
        $second = new Route(Method::Get, '/{value}/b', 'handler');
        $request = new Request(Method::Get, new Uri('/a/b'));
        $this->assertSame($first, new SimpleRouter([$first, $second])->match($request)->route);
        $this->assertSame($second, new SimpleRouter([$second, $first])->match($request)->route);
    }

    #[DataProvider('paths')]
    public function testExactPathMatching(string $pattern, string $uri, bool $matches): void
    {
        $router = new SimpleRouter([new Route(Method::Get, $pattern, 'handler')]);
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
        $get = new Route(Method::Get, '/', 'handler');
        $router = new SimpleRouter([$get]);
        foreach ([Method::Head, Method::Options] as $method) {
            $request = new Request($method, new Uri('/'));
            $this->assertNull($router->match($request));
            $this->assertSame([Method::Get], $router->allowedMethods($request));
        }
        $head = new Route(Method::Head, '/', 'handler');
        $options = new Route(Method::Options, '*', 'handler');
        $router = new SimpleRouter([$get, $head, $options]);
        $this->assertSame($head, $router->match(new Request(Method::Head, new Uri('/')))->route);
        $this->assertSame($options, $router->match(new Request(Method::Options, new Uri('*')))->route);
    }

    public function testUnmatchedAndConnectTargetsHaveNoAllowedMethods(): void
    {
        $router = new SimpleRouter([new Route(Method::Get, '/', 'handler')]);
        foreach ([new Request(Method::Get, new Uri('/missing')), new Request(Method::Connect, new Uri('//host:443'))] as $r) {
            $this->assertNull($router->match($r));
            $this->assertSame([], $router->allowedMethods($r));
        }
    }

    #[DataProvider('duplicates')]
    public function testRejectsDuplicateStructuralRegistrations(string $first, string $second): void
    {
        $this->expectException(DuplicateRouteException::class);
        new SimpleRouter([new Route(Method::Get, $first, 'handler'), new Route(Method::Get, $second, 'handler')]);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function duplicates(): iterable
    {
        yield ['/articles', '/articles'];
        yield ['/articles/{id}', '/articles/{articleId}'];
    }

    public function testRejectsInvalidRegistrations(): void
    {
        $this->expectException(InvalidRouteException::class);
        new SimpleRouter([new stdClass()]);
    }

    public function testRejectsNamedRegistrationMaps(): void
    {
        $route = new Route(Method::Get, '/', 'handler');
        $this->expectException(InvalidRouteException::class);
        new SimpleRouter(['home' => $route]);
    }
}
