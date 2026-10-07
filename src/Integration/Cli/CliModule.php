<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli;

use Override;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides CLI command dispatch, stream output, and worker control service configuration.
 */
final readonly class CliModule implements Module, ConfigurableModule
{
    /**
     * Returns the CLI service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
