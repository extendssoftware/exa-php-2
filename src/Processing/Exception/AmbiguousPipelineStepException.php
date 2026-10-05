<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Exception;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use InvalidArgumentException;

/**
 * Indicates a pipeline step implementing both transformation and validation contracts.
 */
final class AmbiguousPipelineStepException extends InvalidArgumentException implements ProcessingException
{
}
