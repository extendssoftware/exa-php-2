<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\Message\Body\StringBody;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\RequestBodyTooLargeException;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\UnsupportedRequestMediaTypeException;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use Throwable;

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
     * Creates a non-cacheable plain-text response without exposing decoding details.
     *
     * @param Throwable $exception The execution failure.
     * @param Request $request The request at the exception boundary.
     *
     * @return Response A decoding error response or the fallback response.
     *
     * @throws Throwable When the fallback fails, propagated unchanged.
     */
    public function create(Throwable $exception, Request $request): Response
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

        return new Response(
            $status,
            new Headers(['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']),
            new StringBody($message),
            $request->protocolVersion,
        );
    }
}
