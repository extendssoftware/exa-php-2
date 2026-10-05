<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use InvalidArgumentException;

/**
 * Indicates an invalid command middleware list or entry.
 */
final class InvalidCommandMiddlewareException extends InvalidArgumentException implements CqrsException
{
}
