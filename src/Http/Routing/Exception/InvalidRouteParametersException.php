<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use InvalidArgumentException;

/**
 * Indicates invalid parameters for route URL generation.
 */
final class InvalidRouteParametersException extends InvalidArgumentException implements HttpException
{
}
