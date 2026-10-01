<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use LogicException;

/**
 * Indicates that multiple handlers are registered for the same command class.
 */
final class DuplicateCommandHandlerException extends LogicException implements CqrsException
{
}
