<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates an invalid regular expression.
 */
final class InvalidPatternException extends InvalidArgumentException implements ProcessingException
{
}
