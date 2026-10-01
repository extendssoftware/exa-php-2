<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Module;

use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

/**
 * Starts module behavior using the application services.
 */
interface BootstrapModule
{
    /**
     * Starts module behavior using the application services.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return void
     *
     * @throws Throwable When the module cannot complete the operation.
     */
    public function bootstrap(ServiceLocator $services): void;
}
