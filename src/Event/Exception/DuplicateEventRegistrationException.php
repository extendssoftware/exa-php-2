<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event\Exception;

use ExtendsSoftware\ExaPHP\Event\EventException;
use LogicException;

/**
 * Indicates multiple registrations for the same canonical event class.
 */
final class DuplicateEventRegistrationException extends LogicException implements EventException
{
}
