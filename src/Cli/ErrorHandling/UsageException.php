<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\ErrorHandling;

use ExtendsSoftware\ExaPHP\Cli\CliException;

/**
 * Identifies invalid command usage whose message is suitable for terminal presentation.
 */
interface UsageException extends CliException
{
}
