<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ResponseFactory;

use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use Throwable;

/**
 * Encodes application representation data into an HTTP response.
 */
interface ResponseFactory
{
    /**
     * Creates a response with encoded data and its representation headers without emitting it.
     *
     * @param mixed $data The representation data supported by this factory.
     * @param StatusCode $statusCode The response status.
     * @param Headers $headers Additional response headers; representation headers are controlled by the factory.
     *
     * @return Response The encoded response.
     *
     * @throws Throwable When encoding or response creation fails.
     */
    public function create(
        mixed $data,
        StatusCode $statusCode = StatusCode::Ok,
        Headers $headers = new Headers(),
    ): Response;
}
