<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Clock;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Provides the default clock service configuration.
 */
final readonly class ClockModule implements Module, ConfigurableModule
{
    /**
     * Returns the clock service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
