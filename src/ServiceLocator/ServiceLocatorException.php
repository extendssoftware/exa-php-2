<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator;

use Throwable;

/**
 * Identifies failures in service lookup and resolution.
 */
interface ServiceLocatorException extends Throwable
{
}
