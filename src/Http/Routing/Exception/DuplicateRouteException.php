<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Routing\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use LogicException;

/**
 * Indicates duplicate route names or equivalent patterns registered for the same method.
 */
final class DuplicateRouteException extends LogicException implements HttpException
{
}
