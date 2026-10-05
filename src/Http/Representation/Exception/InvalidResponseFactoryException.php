<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Representation\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates invalid response factory registrations or an unavailable default media type.
 */
final class InvalidResponseFactoryException extends InvalidArgumentException implements HttpException
{
}
