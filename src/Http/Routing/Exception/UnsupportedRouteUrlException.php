<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use LogicException;

/**
 * Indicates a route pattern that cannot produce a path-only URL.
 */
final class UnsupportedRouteUrlException extends LogicException implements HttpException
{
}
