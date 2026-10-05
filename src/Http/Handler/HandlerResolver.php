<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Handler;

use ExtendsSoftware\ExaPHP\Http\Handler\Exception\HandlerResolutionException;

/**
 * Resolves request handlers by identifier.
 */
interface HandlerResolver
{
    /**
     * Returns the handler associated with an identifier.
     *
     * @param non-empty-string $id The handler identifier.
     *
     * @return RequestHandler The resolved handler.
     *
     * @throws HandlerResolutionException When the identifier cannot be resolved to a request handler.
     */
    public function resolve(string $id): RequestHandler;
}
