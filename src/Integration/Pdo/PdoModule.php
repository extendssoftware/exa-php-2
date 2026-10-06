<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Pdo;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Provides shared PDO connection and transaction services.
 */
final readonly class PdoModule implements Module, ConfigurableModule
{
    /**
     * Returns the PDO service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
