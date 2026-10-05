<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRouteMatchException;
use ExtendsSoftware\ExaPHP\Http\Exception\RouteParameterNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testRejectsEmptyHandlerIdentifier(): void
    {
        $this->expectException(InvalidRouteException::class);
        new Route(Method::Get, '/', '');
    }

    #[DataProvider('invalidPatterns')]
    public function testRejectsInvalidPatterns(Method $method, string $pattern): void
    {
        $this->expectException(InvalidRouteException::class);
        new Route($method, $pattern, 'handler');
    }

    /**
     * @return iterable<array{Method, string}>
     */
    public static function invalidPatterns(): iterable
    {
        yield [Method::Get, ''];
        yield [Method::Get, 'articles'];
        yield [Method::Get, '/articles?x=1'];
        yield [Method::Get, '/articles#part'];
        yield [Method::Get, '/{id}/{id}'];
        yield [Method::Get, '/prefix-{id}'];
        yield [Method::Get, '/{bad-name}'];
        yield [Method::Get, '/{1id}'];
        yield [Method::Get, '/{}'];
        yield [Method::Get, '/bad%zz'];
        yield [Method::Get, "/bad\r\n"];
        yield [Method::Get, '*'];
        yield [Method::Connect, '/'];
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidMatchParameters(array $parameters): void
    {
        $route = new Route(Method::Get, '/{id}', 'handler');
        $this->expectException(InvalidRouteMatchException::class);
        new RouteMatch($route, $parameters);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidParameters(): iterable
    {
        yield [[]];
        yield [['other' => '1']];
        yield [['id' => '']];
        yield [['id' => 1]];
        yield [['id' => '1', 'other' => '2']];
    }

    public function testMissingParameterThrowsSpecificException(): void
    {
        $match = new RouteMatch(new Route(Method::Get, '/', 'handler'));
        $this->expectException(RouteParameterNotFoundException::class);
        $match->parameter('id');
    }
}
