<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteParametersException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteQueryException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\RouteNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\UnsupportedRouteUrlException;
use Override;

use function array_key_exists;
use function count;
use function http_build_query;
use function is_bool;
use function is_int;
use function is_string;
use function rawurlencode;
use function str_starts_with;
use function strtr;

use const PHP_QUERY_RFC3986;

/**
 * Generates encoded paths from a shared route collection without resolving handlers.
 */
final readonly class RouteUrlGenerator implements UrlGenerator
{
    /**
     * Creates a generator using validated route definitions.
     *
     * @param RouteCollection $routes The shared definitions.
     */
    public function __construct(private RouteCollection $routes)
    {
    }

    /**
     * {@inheritDoc}
     *
     * Uses RFC 3986 query escaping and preserves literal path bytes. Empty and dot-segment parameters are rejected.
     * Asterisk and authority-like patterns beginning with `//` cannot produce path-only URLs.
     *
     * @throws RouteNotFoundException When the route is not registered.
     * @throws InvalidRouteParametersException When parameter names, types, or segment values are invalid.
     * @throws InvalidRouteQueryException When query keys or values are unsupported.
     * @throws UnsupportedRouteUrlException When the pattern cannot produce a path-only URI reference.
     */
    #[Override]
    public function generate(string $routeName, array $parameters = [], array $query = []): Uri
    {
        $route = $this->routes->get($routeName);
        if ($route->path === '*' || str_starts_with($route->path, '//')) {
            throw new UnsupportedRouteUrlException('This route cannot generate a path-only URL.');
        }
        $names = $route->parameterNames();
        if (count($names) !== count($parameters)) {
            throw new InvalidRouteParametersException('Supply exactly the declared route parameters.');
        }
        $replacements = [];
        foreach ($names as $name) {
            if (!array_key_exists($name, $parameters)
                || (!is_string($parameters[$name]) && !is_int($parameters[$name]))) {
                throw new InvalidRouteParametersException('Declared parameters require string or integer values.');
            }
            $value = (string) $parameters[$name];
            if ($value === '' || $value === '.' || $value === '..') {
                throw new InvalidRouteParametersException('Route parameters must not be empty or dot segments.');
            }
            $replacements['{' . $name . '}'] = rawurlencode($value);
        }
        foreach ($query as $key => $value) {
            if (!is_string($key) || $key === ''
                || ($value !== null && !is_string($value) && !is_int($value) && !is_bool($value))) {
                throw new InvalidRouteQueryException('Query keys or value types are unsupported.');
            }
        }
        $path = strtr($route->path, $replacements);
        $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return new Uri($path . ($encoded === '' ? '' : '?' . $encoded));
    }
}
