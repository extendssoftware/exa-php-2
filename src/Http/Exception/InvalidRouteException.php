<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates an invalid route pattern or route registration list.
 */
final class InvalidRouteException extends InvalidArgumentException implements HttpException
{
}
