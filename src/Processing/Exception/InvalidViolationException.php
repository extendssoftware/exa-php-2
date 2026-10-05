<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates invalid violation metadata.
 */
final class InvalidViolationException extends InvalidArgumentException implements ProcessingException
{
}
