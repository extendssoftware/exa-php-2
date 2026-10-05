<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Handler\Exception;

use ExtendsSoftware\ExaPHP\Cli\CliException;
use RuntimeException;

/**
 * Indicates that a CLI handler identifier could not be resolved.
 */
final class HandlerResolutionException extends RuntimeException implements CliException
{
}
