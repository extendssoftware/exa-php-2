<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event\Exception;

use ExtendsSoftware\ExaPHP\Event\EventException;
use InvalidArgumentException;

/**
 * Indicates an invalid event class or listener list.
 */
final class InvalidEventRegistrationException extends InvalidArgumentException implements EventException
{
}
