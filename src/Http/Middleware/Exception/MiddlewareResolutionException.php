<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Middleware\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that a middleware identifier could not be resolved to middleware.
 */
final class MiddlewareResolutionException extends RuntimeException implements HttpException
{
}
