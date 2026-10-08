<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\MiddlewareResolutionException;

/**
 * Resolves request middleware by identifier.
 */
interface MiddlewareResolver
{
    /**
     * Returns the middleware associated with an identifier.
     *
     * @param non-empty-string $id The middleware identifier.
     *
     * @return Middleware The resolved middleware.
     *
     * @throws MiddlewareResolutionException When the identifier cannot be resolved to middleware.
     */
    public function resolve(string $id): Middleware;
}
