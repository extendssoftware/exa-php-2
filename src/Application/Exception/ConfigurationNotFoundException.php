<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use RuntimeException;

/**
 * Indicates that a requested configuration path does not exist.
 */
final class ConfigurationNotFoundException extends RuntimeException implements ApplicationException
{
}
