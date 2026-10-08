<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Event;

use Override;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides service configuration for the event dispatcher.
 */
final readonly class EventModule implements Module, ConfigurableModule
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
