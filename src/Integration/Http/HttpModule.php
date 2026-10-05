<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http;

use Override;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/**
 * Provides HTTP routing, middleware, and PHP server service configuration.
 */
final readonly class HttpModule implements Module, ConfigurableModule
{
    /**
     * Returns the HTTP service configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
