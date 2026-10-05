<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use RuntimeException;

/**
 * Indicates that an application module could not be instantiated.
 */
final class ModuleInstantiationException extends RuntimeException implements ApplicationException
{
}
