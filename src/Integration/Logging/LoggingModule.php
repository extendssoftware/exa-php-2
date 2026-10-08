<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Logging;

use Override;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides logger and default writer service configuration.
 */
final readonly class LoggingModule implements Module, ConfigurableModule
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
