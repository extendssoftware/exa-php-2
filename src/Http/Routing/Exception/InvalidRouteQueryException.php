<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates unsupported URL query data.
 */
final class InvalidRouteQueryException extends InvalidArgumentException implements HttpException
{
}
