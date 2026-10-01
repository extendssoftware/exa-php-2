<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

/**
 * Releases module resources using the application services.
 */
interface ShutdownModule
{
    /**
     * Releases module resources using the application services.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return void
     *
     * @throws Throwable When the module cannot complete the operation.
     */
    public function shutdown(ServiceLocator $services): void;
}
