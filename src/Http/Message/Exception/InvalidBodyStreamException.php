<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates that a body stream is not an open readable stream.
 */
final class InvalidBodyStreamException extends InvalidArgumentException implements HttpException
{
}
