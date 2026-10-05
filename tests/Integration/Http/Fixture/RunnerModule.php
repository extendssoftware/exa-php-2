<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Fixture;

use ExtendsSoftware\ExaPHP\Application\Module\Module;
use ExtendsSoftware\ExaPHP\Application\Module\ShutdownModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

/**
 * Records application shutdown through the runner's test services.
 */
final readonly class RunnerModule implements Module, ShutdownModule
{
    /**
     * Records shutdown and invokes an optional failing cleanup service.
     *
     * @param ServiceLocator $services The application services.
     *
     * @return void
     *
     * @throws Throwable When a configured cleanup fails.
     */
    public function shutdown(ServiceLocator $services): void
    {
        $services->get(RunnerServices::class)->record('shutdown');
        if ($services->has('cleanup')) {
            $services->get('cleanup')->record('shutdown');
        }
    }
}
