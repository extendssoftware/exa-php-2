<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Worker\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use RuntimeException;

/**
 * Indicates failure to manage the CLI worker lifecycle.
 */
final class WorkerControlException extends RuntimeException implements CliException
{
}
