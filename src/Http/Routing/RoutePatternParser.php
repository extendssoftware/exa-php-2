<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidUriException;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;

use function array_keys;
use function explode;
use function implode;
use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * Parses route and group path patterns into validated routing data.
 *
 * @internal
 */
final readonly class RoutePatternParser
{
    /**
     * Validates and parses a path pattern without checking route-specific HTTP method constraints.
     *
     * @param string $path The slash-prefixed pattern or asterisk target.
     *
     * @return array{
     *     segments: list<array{literal: ?string, parameter: ?string}>,
     *     names: list<string>,
     *     signature: string
     * } The parsed segments, parameter names, and structural signature.
     *
     * @throws InvalidRouteException When the pattern or encoded URI path is invalid.
     */
    public function parse(string $path): array
    {
        if (!str_starts_with($path, '/') && $path !== '*') {
            throw new InvalidRouteException('Route patterns must be slash-prefixed paths or an asterisk.');
        }
        if (str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidRouteException('Route patterns must not contain a query or fragment.');
        }
        $segments = [];
        $names = [];
        $signature = [];
        $example = [];
        foreach (explode('/', $path) as $segment) {
            if (preg_match('/\A\{([A-Za-z_][A-Za-z0-9_]*)}\z/', $segment, $matches) === 1) {
                $name = $matches[1];
                if (isset($names[$name])) {
                    throw new InvalidRouteException('Route parameter names must be unique.');
                }
                $names[$name] = true;
                $segments[] = ['literal' => null, 'parameter' => $name];
                $signature[] = '{}';
                $example[] = 'parameter';
            } else {
                if (str_contains($segment, '{') || str_contains($segment, '}')) {
                    throw new InvalidRouteException(
                        'Route placeholders must occupy a full segment and have a valid name.',
                    );
                }
                $segments[] = ['literal' => $segment, 'parameter' => null];
                $signature[] = $segment;
                $example[] = $segment;
            }
        }
        try {
            new Uri('http://routing.invalid' . ($path === '*' ? '/' : implode('/', $example)));
        } catch (InvalidUriException $exception) {
            throw new InvalidRouteException('Route literals must form a valid encoded URI path.', 0, $exception);
        }
        return ['segments' => $segments, 'names' => array_keys($names), 'signature' => implode('/', $signature)];
    }
}
