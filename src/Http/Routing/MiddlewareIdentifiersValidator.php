<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing;

use ExtendsSoftware\ExaPHP\Http\Routing\Exception\InvalidRouteException;

use function array_is_list;
use function is_string;

/**
 * Validates middleware identifier lists shared by routes and groups.
 *
 * @internal
 */
final readonly class MiddlewareIdentifiersValidator
{
    /**
     * Validates an ordered list of middleware identifiers.
     *
     * @param array<array-key, mixed> $middleware The middleware registrations.
     *
     * @return void
     *
     * @throws InvalidRouteException When registrations are not a list of non-empty strings.
     */
    public function validate(array $middleware): void
    {
        if (!array_is_list($middleware)) {
            throw new InvalidRouteException('Route middleware must be a list.');
        }
        foreach ($middleware as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidRouteException('Route middleware identifiers must be non-empty strings.');
            }
        }
    }
}
