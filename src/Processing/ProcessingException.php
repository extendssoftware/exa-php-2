<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use Throwable;

/**
 * Identifies processing configuration and execution failures, excluding ordinary invalid input.
 */
interface ProcessingException extends Throwable
{
}
