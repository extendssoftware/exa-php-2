<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates invalid or duplicate request metadata.
 */
final class InvalidRequestAttributesException extends InvalidArgumentException implements HttpException
{
}
