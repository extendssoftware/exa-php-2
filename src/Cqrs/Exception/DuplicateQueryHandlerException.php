<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use LogicException;

/**
 * Indicates that multiple handlers are registered for the same query class.
 */
final class DuplicateQueryHandlerException extends LogicException implements CqrsException
{
}
