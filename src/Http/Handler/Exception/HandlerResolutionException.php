<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Handler\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that a handler identifier could not be resolved to a request handler.
 */
final class HandlerResolutionException extends RuntimeException implements HttpException
{
}
