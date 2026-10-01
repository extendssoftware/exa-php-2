<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cqrs;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides service configuration for the CQRS buses.
 */
final readonly class CqrsModule implements Module, ConfigurableModule
{
    /**
     * Returns the CQRS service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
