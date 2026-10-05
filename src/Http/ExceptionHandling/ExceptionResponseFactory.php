<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ExceptionHandling;

use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use Throwable;

/**
 * Converts a request-processing failure into an HTTP response.
 */
interface ExceptionResponseFactory
{
    /**
     * Creates a response for a failure without emitting it.
     *
     * @param Throwable $exception The failure to represent.
     * @param Request $request The request available at the exception-handling boundary.
     *
     * @return Response The response representing the failure.
     *
     * @throws Throwable When response creation fails.
     */
    public function create(Throwable $exception, Request $request): Response;
}
