<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Server;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Response;

/**
 * Sends an HTTP response through the server boundary.
 */
interface ResponseEmitter
{
    /**
     * Emits response metadata and permitted body bytes for the originating request method.
     *
     * @param Response $response The outgoing response.
     * @param Method $requestMethod The originating method, including HEAD body suppression.
     *
     * @return void
     *
     * @throws HttpException When emission fails; output already sent cannot be retracted.
     */
    public function emit(Response $response, Method $requestMethod): void;
}
