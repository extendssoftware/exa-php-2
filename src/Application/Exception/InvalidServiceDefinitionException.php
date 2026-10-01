<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use InvalidArgumentException;

/**
 * Indicates that a configured service is not a service definition.
 */
final class InvalidServiceDefinitionException extends InvalidArgumentException implements ApplicationException
{
}
