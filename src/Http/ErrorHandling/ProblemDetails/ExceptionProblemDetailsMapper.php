<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails;

use ExtendsSoftware\ExaPHP\Http\Message\Request;
use Throwable;

/**
 * Maps a supported exception to safe problem details.
 */
interface ExceptionProblemDetailsMapper
{
    /**
     * Maps the original exception or declines it by returning null.
     *
     * @param Throwable $exception The original execution failure.
     * @param Request $request The request available at the exception boundary.
     *
     * @return ProblemDetails|null The public problem description, or null when unhandled.
     *
     * @throws Throwable When mapping fails.
     */
    public function map(Throwable $exception, Request $request): ?ProblemDetails;
}
