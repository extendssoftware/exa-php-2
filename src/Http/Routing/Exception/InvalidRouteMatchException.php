<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates route parameters inconsistent with the matched route.
 */
final class InvalidRouteMatchException extends InvalidArgumentException implements HttpException
{
}
