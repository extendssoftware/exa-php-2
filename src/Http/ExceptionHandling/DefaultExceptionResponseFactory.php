<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ExceptionHandling;

use ExtendsSoftware\ExaPHP\Http\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Headers;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\StatusCode;
use Throwable;

/**
 * Represents every failure as a generic plain-text 500 response without exception details.
 */
final readonly class DefaultExceptionResponseFactory implements ExceptionResponseFactory
{
    /**
     * Creates a non-cacheable response retaining the request protocol version.
     *
     * Exception messages, codes, types, and traces do not affect the response. No logging is performed.
     *
     * @param Throwable $exception The failure, whose details are not exposed.
     * @param Request $request The request providing the protocol version.
     *
     * @return Response A generic Internal Server Error response.
     */
    public function create(Throwable $exception, Request $request): Response
    {
        return new Response(
            StatusCode::InternalServerError,
            new Headers(['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']),
            new StringBody('Internal Server Error'),
            $request->protocolVersion,
        );
    }
}
