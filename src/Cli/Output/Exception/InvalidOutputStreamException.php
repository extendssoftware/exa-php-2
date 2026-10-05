<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Output\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use InvalidArgumentException;

/**
 * Indicates that an output destination is not an open writable stream.
 */
final class InvalidOutputStreamException extends InvalidArgumentException implements CliException
{
}
