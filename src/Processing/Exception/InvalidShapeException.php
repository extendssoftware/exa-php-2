<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates invalid shape field definitions.
 */
final class InvalidShapeException extends InvalidArgumentException implements ProcessingException
{
}
