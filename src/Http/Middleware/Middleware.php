<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware;

use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use Throwable;

/**
 * Processes an HTTP request around a subsequent handler or returns a response directly.
 */
interface Middleware
{
    /**
     * Processes the request with optional delegation to the next handler.
     *
     * @param Request $request The incoming request.
     * @param RequestHandler $next The next handler available for delegation.
     *
     * @return Response The resulting response without emission.
     *
     * @throws HttpException When HTTP processing fails.
     * @throws Throwable When application or delegated execution fails, propagated unchanged.
     */
    public function process(Request $request, RequestHandler $next): Response;
}
