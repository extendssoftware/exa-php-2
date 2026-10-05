<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use LogicException;

/**
 * Indicates that a module class has already been registered.
 */
final class DuplicateModuleException extends LogicException implements ApplicationException
{
}
