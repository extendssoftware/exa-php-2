<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Exception;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use LogicException;

/**
 * Indicates that service resolution depends on an identifier already being resolved.
 */
final class CircularDependencyException extends LogicException implements ServiceLocatorException
{
}
