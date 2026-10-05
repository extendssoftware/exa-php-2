<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates a URI unsuitable for an HTTP request.
 */
final class InvalidRequestException extends InvalidArgumentException implements HttpException
{
}
