<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Registers required HTTP authentication with Bearer credential extraction and failure responses.
 */
final readonly class AuthenticationModule implements Module, ConfigurableModule
{
    /**
     * Returns the authentication integration configuration directory.
     *
     * @return non-empty-string The absolute configuration directory path.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
