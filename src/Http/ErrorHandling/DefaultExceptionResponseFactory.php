<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\ResponseEncodingException;
use Override;
use Throwable;
use SensitiveParameter;

/**
 * Represents every failure as a generic Problem Details 500 response without exception details.
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
     *
     * @throws ResponseEncodingException When encoding fails.
     */
    #[Override]
    public function create(Throwable $exception, #[SensitiveParameter] Request $request): Response
    {
        return new ProblemDetailsResponseFactory()->create(
            new ProblemDetails(StatusCode::InternalServerError, 'Internal Server Error'),
            protocolVersion: $request->protocolVersion,
        );
    }
}
