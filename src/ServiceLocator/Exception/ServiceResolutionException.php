<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Exception;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use RuntimeException;

/**
 * Indicates that service inspection, construction, or delegation failed.
 */
final class ServiceResolutionException extends RuntimeException implements ServiceLocatorException
{
}
