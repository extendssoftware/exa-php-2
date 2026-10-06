<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\DuplicateRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\RouteNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class RouteCollectionTest extends TestCase
{
    public function testNamesAreUniqueAcrossMethodsAndPaths(): void
    {
        $this->expectException(DuplicateRouteException::class);
        new RouteCollection([
            new Route('article', Method::Get, '/articles', 'handler'),
            new Route('article', Method::Post, '/other', 'handler'),
        ]);
    }

    public function testKeepsDefinitionOrderAndIdentity(): void
    {
        $first = new Route('first', Method::Post, '/articles', 'create');
        $second = new Route('second', Method::Get, '/articles', 'list');
        $collection = new RouteCollection([$first, $second]);
        $this->assertSame([$first, $second], $collection->all());
        $this->assertSame($first, $collection->get('first'));
        $this->expectException(RouteNotFoundException::class);
        $collection->get('First');
    }

    public function testRejectsEmptyNames(): void
    {
        $this->expectException(InvalidRouteException::class);
        new Route('', Method::Get, '/', 'handler');
    }
    #[DataProvider('duplicates')]
    public function testRejectsDuplicateStructuralRegistrations(string $first, string $second): void
    {
        $this->expectException(DuplicateRouteException::class);
        new RouteCollection([
            new Route('route.13', Method::Get, $first, 'handler'),
            new Route('route.14', Method::Get, $second, 'handler'),
        ]);
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
        new RouteCollection([new stdClass()]);
    }

    public function testRejectsNamedRegistrationMaps(): void
    {
        $route = new Route('route.15', Method::Get, '/', 'handler');
        $this->expectException(InvalidRouteException::class);
        new RouteCollection(['home' => $route]);
    }
}
