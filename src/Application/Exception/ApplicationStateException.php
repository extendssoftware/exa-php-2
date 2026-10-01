<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Exception;

use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use LogicException;

/**
 * Indicates that an application operation is not valid in its current bootstrap state.
 */
final class ApplicationStateException extends LogicException implements ApplicationException
{
}
