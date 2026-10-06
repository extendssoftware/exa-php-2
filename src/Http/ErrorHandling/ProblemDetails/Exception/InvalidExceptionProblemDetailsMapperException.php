<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates invalid exception problem mapper registrations.
 */
final class InvalidExceptionProblemDetailsMapperException extends InvalidArgumentException implements HttpException
{
}
