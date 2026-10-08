<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;

use Override;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use Throwable;
use SensitiveParameter;

/**
 * Maps request decoding failures to safe 400, 413, or 415 responses and delegates other failures.
 */
final readonly class RequestBodyExceptionResponseFactory implements ExceptionResponseFactory
{
    /**
     * Creates the decoding failure response policy.
     *
     * @param ExceptionResponseFactory $fallback The policy for unrelated failures.
     */
    public function __construct(private ExceptionResponseFactory $fallback)
    {
    }

    /**
     * {@inheritDoc}
     *
     * Responses are non-cacheable Problem Details documents.
     */
    #[Override]
    public function create(Throwable $exception, #[SensitiveParameter] Request $request): Response
    {
        [$status, $message] = match (true) {
            $exception instanceof MalformedRequestBodyException => [StatusCode::BadRequest, 'Bad Request'],
            $exception instanceof RequestBodyTooLargeException => [StatusCode::ContentTooLarge, 'Content Too Large'],
            $exception instanceof UnsupportedRequestMediaTypeException => [
                StatusCode::UnsupportedMediaType, 'Unsupported Media Type',
            ],
            default => [null, null],
        };
        if ($status === null) {
            return $this->fallback->create($exception, $request);
        }

        return new ProblemDetailsResponseFactory()->create(
            new ProblemDetails($status, $message),
            protocolVersion: $request->protocolVersion,
        );
    }
}
