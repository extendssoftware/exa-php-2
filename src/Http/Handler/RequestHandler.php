<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Handler;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Throwable;

/**
 * Handles an HTTP request and produces its response without emitting it.
 */
interface RequestHandler
{
    /**
     * Handles the request and returns the response.
     *
     * @param Request $request The incoming request.
     *
     * @return Response The response to the request.
     *
     * @throws HttpException When HTTP handling fails.
     * @throws Throwable When application execution fails, propagated unchanged.
     */
    public function handle(Request $request): Response;
}
