<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that the request body exceeds the configured decoding limit.
 */
final class RequestBodyTooLargeException extends RuntimeException implements HttpException
{
}
