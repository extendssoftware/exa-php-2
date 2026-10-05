<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Decoding\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that the request body cannot be decoded in its declared format.
 */
final class MalformedRequestBodyException extends RuntimeException implements HttpException
{
}
