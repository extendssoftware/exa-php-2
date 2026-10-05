<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates a failed or unsupported body stream read.
 */
final class BodyReadException extends RuntimeException implements HttpException
{
}
