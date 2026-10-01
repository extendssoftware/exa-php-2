<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use InvalidArgumentException;

/**
 * Indicates that a query handler registration has an invalid query class or handler.
 */
final class InvalidQueryRegistrationException extends InvalidArgumentException implements CqrsException
{
}
