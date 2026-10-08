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
use SensitiveParameter;

/**
 * Maps access denials to generic forbidden problem details.
 */
final readonly class AccessDeniedProblemDetailsMapper implements ExceptionProblemDetailsMapper
{
    /**
     * {@inheritDoc}
     *
     * Declines unrelated failures, including invalid authorization requests and evaluation failures.
     */
    #[Override]
    public function map(Throwable $exception, #[SensitiveParameter] Request $request): ?ProblemDetails
    {
        if (!$exception instanceof AccessDeniedException) {
            return null;
        }

        return new ProblemDetails(StatusCode::Forbidden, 'Forbidden');
    }
}
