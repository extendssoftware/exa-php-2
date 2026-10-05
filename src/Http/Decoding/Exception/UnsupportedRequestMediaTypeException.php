<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Decoding\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates a missing, malformed, or unsupported request content type or encoding.
 */
final class UnsupportedRequestMediaTypeException extends RuntimeException implements HttpException
{
}
