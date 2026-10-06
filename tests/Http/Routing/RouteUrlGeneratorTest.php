<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteParametersException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteQueryException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\RouteNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\UnsupportedRouteUrlException;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteUrlGenerator;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteUrlGeneratorTest extends TestCase
{
    public function testEncodesSegmentsAndQueryWithoutChangingLiteralPaths(): void
    {
        $route = new Route('article', Method::Get, '/a%20b/{id}/{tag}/', 'missing');
        $routes = new RouteCollection([$route]);
        $uri = new RouteUrlGenerator($routes)->generate('article', ['tag' => 'é /?#%', 'id' => 0], [
            'search' => 'a b&c', 'page' => 2, 'enabled' => false, 'extra' => null,
        ]);
        $this->assertSame('/a%20b/0/%C3%A9%20%2F%3F%23%25/?search=a%20b%26c&page=2&enabled=0', $uri->toString());
        $this->assertNull($uri->scheme());
        $this->assertNull($uri->host());
        $match = new SimpleRouter($routes)->match(new Request(Method::Get, $uri));
        $this->assertSame($route, $match->route);
        $this->assertSame(['id' => '0', 'tag' => '%C3%A9%20%2F%3F%23%25'], $match->parameters);
    }

    public function testOmitsEmptyQueryAndSupportsLiteralRoutes(): void
    {
        $generator = new RouteUrlGenerator(new RouteCollection([new Route('home', Method::Get, '/', 'handler')]));
        $this->assertSame('/', $generator->generate('home', query: ['optional' => null])->toString());
    }

    public function testRejectsUnknownNames(): void
    {
        $this->expectException(RouteNotFoundException::class);
        new RouteUrlGenerator(new RouteCollection())->generate('missing');
    }

    /** @param array<array-key, mixed> $parameters */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidParameters(array $parameters): void
    {
        $generator = new RouteUrlGenerator(new RouteCollection([
            new Route('article', Method::Get, '/articles/{id}', 'handler'),
        ]));
        $this->expectException(InvalidRouteParametersException::class);
        $generator->generate('article', $parameters);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidParameters(): iterable
    {
        yield [[]];
        yield [['other' => 1]];
        yield [['id' => 1, 'extra' => 2]];
        yield [['id' => '']];
        yield [['id' => '.']];
        yield [['id' => '..']];
        yield [['id' => false]];
        yield [['id' => null]];
        yield [['id' => []]];
    }

    /** @param array<array-key, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function testRejectsUnsupportedQueryData(array $query): void
    {
        $generator = new RouteUrlGenerator(new RouteCollection([new Route('home', Method::Get, '/', 'handler')]));
        $this->expectException(InvalidRouteQueryException::class);
        $generator->generate('home', query: $query);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield [['' => 'value']];
        yield [[0 => 'value']];
        yield [['nested' => ['value']]];
        yield [['float' => 1.5]];
    }

    #[DataProvider('unsupportedPatterns')]
    public function testRejectsNonPathReferences(string $pattern): void
    {
        $generator = new RouteUrlGenerator(new RouteCollection([
            new Route('special', Method::Options, $pattern, 'handler'),
        ]));
        $this->expectException(UnsupportedRouteUrlException::class);
        $generator->generate('special');
    }

    /** @return iterable<array{string}> */
    public static function unsupportedPatterns(): iterable
    {
        yield ['*'];
        yield ['//example.com/path'];
    }
}
