<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use InvalidArgumentException;

/**
 * Indicates that configuration has an invalid structure.
 */
final class InvalidConfigurationException extends InvalidArgumentException implements ApplicationException
{
}
