<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates an unknown route name.
 */
final class RouteNotFoundException extends RuntimeException implements HttpException
{
}
