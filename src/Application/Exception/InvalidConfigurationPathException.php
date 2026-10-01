<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use InvalidArgumentException;

/**
 * Indicates that a configuration path contains an empty segment.
 */
final class InvalidConfigurationPathException extends InvalidArgumentException implements ApplicationException
{
}
