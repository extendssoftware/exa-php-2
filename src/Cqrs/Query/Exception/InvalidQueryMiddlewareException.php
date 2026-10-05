<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use InvalidArgumentException;

/**
 * Indicates an invalid query middleware list or entry.
 */
final class InvalidQueryMiddlewareException extends InvalidArgumentException implements CqrsException
{
}
