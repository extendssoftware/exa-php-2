<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that the server environment cannot produce a request.
 */
final class RequestCreationException extends RuntimeException implements HttpException
{
}
