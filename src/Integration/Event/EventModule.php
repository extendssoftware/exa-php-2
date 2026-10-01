<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Event;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides service configuration for the event dispatcher.
 */
final readonly class EventModule implements Module, ConfigurableModule
{
    /**
     * Returns the event service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
