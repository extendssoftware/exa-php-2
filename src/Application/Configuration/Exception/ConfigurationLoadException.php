<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Configuration\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use RuntimeException;

/**
 * Indicates that configuration discovery or file execution failed.
 */
final class ConfigurationLoadException extends RuntimeException implements ApplicationException
{
}
