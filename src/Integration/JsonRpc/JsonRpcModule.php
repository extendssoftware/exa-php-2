<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\JsonRpc;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Provides application configuration for shared JSON-RPC message, dispatch, and server services.
 */
final readonly class JsonRpcModule implements Module, ConfigurableModule
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
