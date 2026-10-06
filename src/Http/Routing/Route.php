<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;
use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidUriException;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;

use function array_keys;
use function count;
use function explode;
use function implode;
use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * Binds one method and an exact path pattern to a handler identifier.
 */
final readonly class Route
{
    /**
     * Pattern segments containing either a literal or a parameter name.
     *
     * @var list<array{literal: ?string, parameter: ?string}>
     */
    private array $segments;

    /**
     * Parameter names in path order.
     *
     * @var list<string>
     */
    private array $names;

    /**
     * Canonical pattern with parameter names replaced by empty braces.
     *
     * @var string
     */
    private string $signature;

    /**
     * Creates a route with full-segment placeholders such as /articles/{id}.
     *
     * @param non-empty-string $name The unique, case-sensitive route identifier used for URL generation.
     * @param Method $method The method matched exactly; CONNECT is not supported by path routing.
     * @param string $path The encoded absolute path pattern, or a bare asterisk for OPTIONS.
     * @param non-empty-string $handlerId The handler identifier resolved on a successful match.
     *
     * @throws InvalidRouteException When the identifier is empty, the pattern is invalid, or the method is unsupported.
     */
    public function __construct(
        public string $name,
        public Method $method,
        public string $path,
        public string $handlerId,
    )
    {
        if ($name === '') {
            throw new InvalidRouteException('Route names must not be empty.');
        }
        if ($handlerId === '') {
            throw new InvalidRouteException('Route handler identifiers must not be empty.');
        }
        if ($method === Method::Connect
            || (!str_starts_with($path, '/') && !($path === '*' && $method === Method::Options))) {
            throw new InvalidRouteException(
                'Routes require a slash-prefixed path or OPTIONS asterisk; CONNECT is unsupported.',
            );
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
        $this->segments = $segments;
        $this->names = array_keys($names);
        $this->signature = implode('/', $signature);
    }

    /**
     * Returns the structural pattern used to identify equivalent registrations.
     *
     * @return string The pattern with parameter names replaced by empty braces.
     */
    public function signature(): string
    {
        return $this->signature;
    }

    /**
     * Returns the number of literal segments used for route precedence.
     *
     * @return int<0, max> The literal-segment count.
     */
    public function specificity(): int
    {
        return count($this->segments) - count($this->names);
    }

    /**
     * Returns parameter names in pattern order.
     *
     * @return list<string> The declared parameter names.
     */
    public function parameterNames(): array
    {
        return $this->names;
    }

    /**
     * Matches a raw path without decoding, normalization, or parameter value validation.
     *
     * @param string $path The encoded request path.
     *
     * @return array<string, non-empty-string>|null Extracted parameters, or null when the path does not match.
     */
    public function matchPath(string $path): ?array
    {
        $parts = explode('/', $path);
        if (count($parts) !== count($this->segments)) {
            return null;
        }
        $parameters = [];
        foreach ($this->segments as $index => $segment) {
            if ($segment['parameter'] !== null) {
                if ($parts[$index] === '') {
                    return null;
                }
                $parameters[$segment['parameter']] = $parts[$index];
            } elseif ($segment['literal'] !== $parts[$index]) {
                return null;
            }
        }

        return $parameters;
    }
}
