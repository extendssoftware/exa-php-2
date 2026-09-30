<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Exception;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use RuntimeException;

/**
 * Indicates that a requested service identifier is not registered.
 */
final class ServiceNotFoundException extends RuntimeException implements ServiceLocatorException
{
}
