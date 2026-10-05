<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates reversed integer range bounds.
 */
final class InvalidIntegerRangeException extends InvalidArgumentException implements ProcessingException
{
}
