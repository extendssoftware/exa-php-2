<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authorization\Http;

use ExtendsSoftware\ExaPHP\Authorization\Exception\AccessDeniedException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use Override;
use Throwable;

/**
 * Maps access denials to generic forbidden problem details.
 */
final readonly class AccessDeniedProblemDetailsMapper implements ExceptionProblemDetailsMapper
{
    /**
     * Maps access denials without exposing the actor, action, resource, or exception details.
     *
     * Other exceptions are declined, including invalid requests and authorization evaluation failures.
     *
     * @param Throwable $exception The original execution failure.
     * @param Request $request The HTTP request at the exception boundary.
     *
     * @return ProblemDetails|null A generic 403 problem for access denial, or null for unrelated failures.
     */
    #[Override]
    public function map(Throwable $exception, Request $request): ?ProblemDetails
    {
        if (!$exception instanceof AccessDeniedException) {
            return null;
        }

        return new ProblemDetails(StatusCode::Forbidden, 'Forbidden');
    }
}
