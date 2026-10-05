<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates an invalid HTTP middleware registration list.
 */
final class InvalidMiddlewareException extends InvalidArgumentException implements HttpException
{
}
