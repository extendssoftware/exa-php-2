<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Routing\Exception\DuplicateRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\RouteNotFoundException;

use function array_is_list;
use function array_values;
use function sprintf;

/**
 * Shares validated route definitions between matching and URL generation.
 */
final readonly class RouteCollection
{
    /**
     * Routes indexed by name in registration order.
     *
     * @var array<string, Route>
     */
    private array $routes;

    /**
     * Creates a collection without resolving handlers.
     *
     * @param list<Route> $routes The route definitions.
     *
     * @throws InvalidRouteException When registrations are not a list of routes.
     * @throws DuplicateRouteException When names or method/pattern combinations repeat.
     */
    public function __construct(array $routes = [])
    {
        if (!array_is_list($routes)) {
            throw new InvalidRouteException('Routes must be a list.');
        }
        $named = [];
        $patterns = [];
        foreach ($routes as $route) {
            if (!$route instanceof Route) {
                throw new InvalidRouteException('Every registration must be a Route.');
            }
            if (isset($named[$route->name])) {
                throw new DuplicateRouteException(sprintf('Route name "%s" is already registered.', $route->name));
            }
            $key = $route->method->value . ' ' . $route->signature();
            if (isset($patterns[$key])) {
                throw new DuplicateRouteException(sprintf('A route is already registered for %s.', $key));
            }
            $named[$route->name] = $route;
            $patterns[$key] = true;
        }
        $this->routes = $named;
    }

    /**
     * Returns the named definition.
     *
     * @param string $name The exact route name.
     *
     * @return Route The registered route.
     *
     * @throws RouteNotFoundException When the name is not registered.
     */
    public function get(string $name): Route
    {
        return $this->routes[$name]
            ?? throw new RouteNotFoundException(sprintf('Route "%s" is not registered.', $name));
    }

    /**
     * Returns definitions in registration order.
     *
     * @return list<Route> The routes.
     */
    public function all(): array
    {
        return array_values($this->routes);
    }
}
