<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates an invalid URI reference.
 */
final class InvalidUriException extends InvalidArgumentException implements HttpException
{
}
