<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use RuntimeException;

/**
 * Indicates that no handler is registered for a command's exact class.
 */
final class CommandHandlerNotFoundException extends RuntimeException implements CqrsException
{
}
