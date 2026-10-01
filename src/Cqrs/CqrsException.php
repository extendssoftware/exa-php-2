<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs;

use Throwable;

/**
 * Identifies command and query dispatch failures.
 */
interface CqrsException extends Throwable
{
}
