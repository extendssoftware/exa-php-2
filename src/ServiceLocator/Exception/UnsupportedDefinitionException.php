<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Exception;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use InvalidArgumentException;

/**
 * Indicates that a resolver does not support the supplied service definition.
 */
final class UnsupportedDefinitionException extends InvalidArgumentException implements ServiceLocatorException
{
}
