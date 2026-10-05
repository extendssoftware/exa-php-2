<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use OutOfBoundsException;

/**
 * Indicates a parameter absent from a route match.
 */
final class RouteParameterNotFoundException extends OutOfBoundsException implements HttpException
{
}
