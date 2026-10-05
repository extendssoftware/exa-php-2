<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates a nested processing step implementing more than one processing role.
 */
final class AmbiguousProcessingStepException extends InvalidArgumentException implements ProcessingException
{
}
