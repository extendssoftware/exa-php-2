<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates an invalid header name or field value.
 */
final class InvalidHeaderException extends InvalidArgumentException implements HttpException
{
}
