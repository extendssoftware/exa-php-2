<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging;

use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Override;

/**
 * Registers subscriptions, subscriber resolution, and a CLI consumer using application-provided transport and retries.
 */
final readonly class MessagingModule implements Module, ConfigurableModule
{
    /**
     * Returns the messaging service and command configuration directory.
     *
     * @return non-empty-string The absolute configuration directory.
     */
    #[Override]
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
