<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use Override;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;

use function array_map;
use function usort;

/**
 * Selects exact path patterns by literal specificity, then by explicit HTTP method.
 */
final readonly class SimpleRouter implements Router
{
    /**
     * Routes sorted by descending literal-segment count, retaining registration order for ties.
     *
     * @var list<Route>
     */
    private array $routes;

    /**
     * Creates a router without executing route handlers.
     *
     * @param RouteCollection $routes The validated routes in registration order.

     */
    public function __construct(RouteCollection $routes)
    {
        $routes = $routes->all();
        usort($routes, static fn(Route $left, Route $right): int => $right->specificity() <=> $left->specificity());
        $this->routes = $routes;
    }

    /**
     * Matches the most specific path before selecting an explicitly registered method.
     *
     * Literal segments outrank placeholders; tied overlapping patterns use registration order. The winning structural
     * pattern reserves the path across methods. HEAD and OPTIONS have no implicit fallback. CONNECT is not routed.
     *
     * @param Request $request The request whose raw URI path and method are matched; query and host are ignored.
     *
     * @return RouteMatch|null The route match, or null for an unknown path or unsupported method.
     */
    #[Override]
    public function match(Request $request): ?RouteMatch
    {
        foreach ($this->matchingRoutes($request) as $candidate) {
            if ($candidate['route']->method === $request->method) {
                return new RouteMatch($candidate['route'], $candidate['parameters']);
            }
        }

        return null;
    }

    /**
     * Returns explicit methods for the winning structural path pattern, in registration order.
     *
     * @param Request $request The request target to inspect.
     *
     * @return list<Method> The supported methods, or an empty list for an unrouted target.
     */
    #[Override]
    public function allowedMethods(Request $request): array
    {
        return array_map(
            static fn(array $candidate): Method => $candidate['route']->method,
            $this->matchingRoutes($request),
        );
    }

    /**
     * Finds registrations for the highest-priority matching structural pattern.
     *
     * @param Request $request The request target to inspect.
     *
     * @return list<array{route: Route, parameters: array<string, non-empty-string>}> Matching registrations.
     */
    private function matchingRoutes(Request $request): array
    {
        if ($request->method === Method::Connect) {
            return [];
        }
        $path = $request->uri->path() === '' ? '/' : $request->uri->path();
        $signature = null;
        $matches = [];
        foreach ($this->routes as $route) {
            if ($signature !== null && $signature !== $route->signature()) {
                continue;
            }
            $parameters = $route->matchPath($path);
            if ($parameters !== null) {
                $signature = $route->signature();
                $matches[] = ['route' => $route, 'parameters' => $parameters];
            }
        }

        return $matches;
    }
}
