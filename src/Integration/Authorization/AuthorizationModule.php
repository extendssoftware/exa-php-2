<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authorization;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Registers the authorization guard and HTTP access-denial mapping.
 */
final readonly class AuthorizationModule implements Module, ConfigurableModule
{
    /**
     * Returns the authorization integration configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
