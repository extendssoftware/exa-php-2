<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRouteMatchException;
use ExtendsSoftware\ExaPHP\Http\Exception\RouteParameterNotFoundException;

use function count;
use function is_string;
use function sprintf;

/**
 * Carries a matched route and its encoded string parameters as immutable request metadata.
 */
final readonly class RouteMatch
{
    /**
     * Creates a match containing exactly the parameters declared by the route.
     *
     * @param Route $route The selected route.
     * @param array<string, non-empty-string> $parameters Extracted, encoded parameter values.
     *
     * @throws InvalidRouteMatchException When required parameters are missing, invalid, or unexpected.
     */
    public function __construct(public Route $route, public array $parameters = [])
    {
        $names = $route->parameterNames();
        if (count($names) !== count($parameters)) {
            throw new InvalidRouteMatchException('Route match parameters must exactly match the route placeholders.');
        }
        foreach ($names as $name) {
            if (!isset($parameters[$name]) || !is_string($parameters[$name]) || $parameters[$name] === '') {
                throw new InvalidRouteMatchException(
                    'Route match parameters must contain non-empty strings for every placeholder.',
                );
            }
        }
    }

    /**
     * Returns an extracted parameter without decoding or type conversion.
     *
     * @param string $name The placeholder name.
     *
     * @return non-empty-string The encoded parameter value.
     *
     * @throws RouteParameterNotFoundException When the placeholder is absent.
     */
    public function parameter(string $name): string
    {
        return $this->parameters[$name]
            ?? throw new RouteParameterNotFoundException(sprintf('Route parameter "%s" is not present.', $name));
    }
}
