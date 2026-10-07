<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Identity\Exception;

use ExtendsSoftware\ExaPHP\Identity\IdentityException;
use InvalidArgumentException;

/**
 * Indicates that an actor contains an empty identifier or kind.
 */
final class InvalidActorException extends InvalidArgumentException implements IdentityException
{
}
