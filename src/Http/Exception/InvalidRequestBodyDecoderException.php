<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates invalid decoder registrations or decoding limits.
 */
final class InvalidRequestBodyDecoderException extends InvalidArgumentException implements HttpException
{
}
