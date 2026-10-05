<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates invalid string length bounds.
 */
final class InvalidStringLengthException extends InvalidArgumentException implements ProcessingException
{
}
