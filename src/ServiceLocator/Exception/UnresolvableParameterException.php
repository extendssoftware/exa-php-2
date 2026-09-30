<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Exception;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use RuntimeException;

/**
 * Indicates that a constructor parameter cannot be supplied by reflection-based service resolution.
 */
final class UnresolvableParameterException extends RuntimeException implements ServiceLocatorException
{
}
