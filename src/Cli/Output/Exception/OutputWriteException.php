<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Output\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use RuntimeException;

/**
 * Indicates that writing CLI output failed before all bytes were delivered.
 */
final class OutputWriteException extends RuntimeException implements CliException
{
}
