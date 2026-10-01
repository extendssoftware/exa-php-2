<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Event;

use Throwable;

/**
 * Identifies event dispatch failures.
 */
interface EventException extends Throwable
{
}
