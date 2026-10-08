<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Outbox;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Registers the outbox processor and CLI worker using application-provided adapters and retry policy.
 */
final readonly class OutboxModule implements Module, ConfigurableModule
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
