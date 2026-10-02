<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Logging;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides logger and default writer service configuration.
 */
final readonly class LoggingModule implements Module, ConfigurableModule
{
    /**
     * Returns the logging service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
