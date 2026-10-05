<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use InvalidArgumentException;

/**
 * Indicates that a command handler registration has an invalid command class or handler.
 */
final class InvalidCommandRegistrationException extends InvalidArgumentException implements CqrsException
{
}
