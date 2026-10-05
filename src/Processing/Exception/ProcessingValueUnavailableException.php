<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use LogicException;

/**
 * Indicates an attempt to read a value from unsuccessful processing.
 */
final class ProcessingValueUnavailableException extends LogicException implements ProcessingException
{
}
