<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Configuration\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use LogicException;

/**
 * Indicates that multiple modules define the same service identifier.
 */
final class DuplicateServiceDefinitionException extends LogicException implements ApplicationException
{
}
