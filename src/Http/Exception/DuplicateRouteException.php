<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use LogicException;

/**
 * Indicates equivalent route patterns registered for the same method.
 */
final class DuplicateRouteException extends LogicException implements HttpException
{
}
