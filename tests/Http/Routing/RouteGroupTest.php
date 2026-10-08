<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\DuplicateRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteGroup;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteGroupTest extends TestCase
{
    public function testExpandsNestedPrefixesAndInheritedMiddlewareWithoutChangingNamesOrChildren(): void
    {
        $child = new Route('article', Method::Get, '/{id}', 'handler', ['route']);
        $group = new RouteGroup('/tenants/{tenant}', ['outer'], [
            new RouteGroup('/articles', ['inner', 'outer'], [$child]),
            new Route('index', Method::Get, '/', 'index'),
        ]);
        $routes = $group->expand();
        self::assertSame(['outer', 'inner', 'outer', 'route'], $routes[0]->middleware);
        self::assertSame('/tenants/{tenant}/articles/{id}', $routes[0]->path);
        self::assertSame('article', $routes[0]->name);
        self::assertSame('handler', $routes[0]->handlerId);
        self::assertSame('/tenants/{tenant}/', $routes[1]->path);
        self::assertSame('/{id}', $child->path);
        self::assertSame(['route'], $child->middleware);
        $generator = new RouteUrlGenerator(new RouteCollection($routes));
        self::assertSame('/tenants/one/articles/42', $generator->generate('article', [
            'tenant' => 'one', 'id' => '42',
        ])->toString());
    }

    public function testEmptyPrefixAllowsAsteriskRoutesAndEmptyGroups(): void
    {
        $route = new Route('options', Method::Options, '*', 'handler');
        self::assertSame('*', new RouteGroup(routes: [$route])->expand()[0]->path);
        self::assertSame([], new RouteGroup()->expand());
    }

    /** @param array<mixed> $middleware @param array<mixed> $routes */
    #[DataProvider('invalidGroups')]
    public function testRejectsInvalidGroups(string $prefix, array $middleware, array $routes): void
    {
        $this->expectException(InvalidRouteException::class);
        new RouteGroup($prefix, $middleware, $routes)->expand();
    }

    /** @return iterable<string, array{string, array<mixed>, array<mixed>}> */
    public static function invalidGroups(): iterable
    {
        yield 'relative prefix' => ['admin', [], []];
        yield 'asterisk prefix' => ['*', [], []];
        yield 'repeated prefix parameter' => ['/{id}/{id}', [], []];
        yield 'invalid URI literal' => ['/bad path', [], []];
        yield 'trailing slash' => ['/admin/', [], []];
        yield 'root slash' => ['/', [], []];
        yield 'query' => ['/admin?query', [], []];
        yield 'invalid placeholder' => ['/{bad-name}', [], []];
        yield 'keyed middleware' => ['', ['key' => 'middleware'], []];
        yield 'empty middleware' => ['', [''], []];
        yield 'non-string middleware' => ['', [42], []];
        yield 'keyed children' => ['', [], ['key' => new Route('one', Method::Get, '/', 'handler')]];
        yield 'invalid child' => ['', [], ['invalid']];
        yield 'prefixed asterisk' => ['/admin', [], [new Route('one', Method::Options, '*', 'handler')]];
        yield 'repeated parameter' => ['/{id}', [], [new Route('one', Method::Get, '/{id}', 'handler')]];
    }

    public function testExpandedRoutesStillRejectDuplicatePatterns(): void
    {
        $group = new RouteGroup('/admin', routes: [
            new Route('one', Method::Get, '/{id}', 'handler'),
            new Route('two', Method::Get, '/{name}', 'handler'),
        ]);
        $this->expectException(DuplicateRouteException::class);
        new RouteCollection($group->expand());
    }

    /** @param array<mixed> $middleware */
    #[DataProvider('invalidMiddleware')]
    public function testRejectsInvalidRouteMiddleware(array $middleware): void
    {
        $this->expectException(InvalidRouteException::class);
        new Route('one', Method::Get, '/', 'handler', $middleware);
    }

    /** @return iterable<array{array<mixed>}> */
    public static function invalidMiddleware(): iterable
    {
        yield [['key' => 'middleware']];
        yield [['']];
        yield [[null]];
    }
}
