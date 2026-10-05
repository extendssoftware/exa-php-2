<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Fixture;

use ExtendsSoftware\ExaPHP\Application\Module\Module;
use ExtendsSoftware\ExaPHP\Application\Module\ShutdownModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

/**
 * Invokes the runner probe during application shutdown.
 */
final readonly class RunnerModule implements Module, ShutdownModule
{
    /**
     * Shuts down the probe service.
     *
     * @param ServiceLocator $services The application services.
     *
     * @return void
     *
     * @throws Throwable When the probe fails.
     */
    public function shutdown(ServiceLocator $services): void
    {
        $services->get(RunnerHandler::class)->shutdown();
    }
}
