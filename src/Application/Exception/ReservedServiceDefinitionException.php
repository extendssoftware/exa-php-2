<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use InvalidArgumentException;

/**
 * Indicates that configuration attempts to define a service reserved by the application.
 */
final class ReservedServiceDefinitionException extends InvalidArgumentException implements ApplicationException
{
}
