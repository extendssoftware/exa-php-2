<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use InvalidArgumentException;

/**
 * Indicates that a class cannot be registered as an application module.
 */
final class InvalidModuleException extends InvalidArgumentException implements ApplicationException
{
}
