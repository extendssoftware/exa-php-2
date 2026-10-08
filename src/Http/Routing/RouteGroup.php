<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;

use function array_is_list;
use function str_ends_with;
use function str_starts_with;

/**
 * Groups routes under a path prefix and inherited middleware.
 */
final readonly class RouteGroup
{
    /**
     * Creates a group without resolving services or changing route names.
     *
     * Prefixes are empty or slash-prefixed without a trailing slash. Child paths keep their leading slash and are
     * appended literally. Middleware is inherited before child middleware, including repeated identifiers.
     *
     * @param string $prefix The encoded path prefix, allowing full-segment route placeholders.
     * @param list<non-empty-string> $middleware Middleware identifiers in request execution order.
     * @param list<Route|RouteGroup> $routes Child routes or nested groups in declaration order.
     *
     * @throws InvalidRouteException When the prefix, middleware, or child registrations are invalid.
     */
    public function __construct(public string $prefix = '', public array $middleware = [], public array $routes = [])
    {
        if ($prefix !== '' && str_ends_with($prefix, '/')) {
            throw new InvalidRouteException('Group prefixes must not end with a slash; use an empty prefix for root.');
        }
        new MiddlewareIdentifiersValidator()->validate($middleware);
        if ($prefix !== '') {
            if (!str_starts_with($prefix, '/')) {
                throw new InvalidRouteException('Non-empty group prefixes must be slash-prefixed paths.');
            }
            new RoutePatternParser()->parse($prefix);
        }
        if (!array_is_list($routes)) {
            throw new InvalidRouteException('Group children must be a list.');
        }
        foreach ($routes as $route) {
            if (!$route instanceof Route && !$route instanceof self) {
                throw new InvalidRouteException('Group children must be Route or RouteGroup values.');
            }
        }
    }

    /**
     * Expands children into routes with effective paths and middleware in declaration order.
     *
     * Route names remain unchanged. Collection validation detects duplicate names and patterns after expansion.
     *
     * @return list<Route> The expanded routes.
     *
     * @throws InvalidRouteException When combined paths are invalid, including prefixed asterisk routes.
     */
    public function expand(): array
    {
        $expanded = [];
        foreach ($this->routes as $entry) {
            foreach ($entry instanceof self ? $entry->expand() : [$entry] as $route) {
                if ($this->prefix !== '' && $route->path === '*') {
                    throw new InvalidRouteException('Asterisk routes cannot have a group prefix.');
                }
                $expanded[] = new Route(
                    $route->name,
                    $route->method,
                    $this->prefix . $route->path,
                    $route->handlerId,
                    [...$this->middleware, ...$route->middleware],
                );
            }
        }

        return $expanded;
    }
}
