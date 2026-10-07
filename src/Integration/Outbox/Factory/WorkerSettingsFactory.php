<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Outbox\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Exception\InvalidOutboxConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function is_array;
use function is_int;

/**
 * Reads outbox worker durations from application configuration.
 */
final readonly class WorkerSettingsFactory
{
    /**
     * Creates validated worker settings from outbox.worker configuration.
     *
     * @param ServiceLocator $serviceLocator The locator providing application configuration.
     *
     * @return WorkerSettings The configured worker settings.
     *
     * @throws InvalidOutboxConfigurationException When settings are missing or have invalid types or values.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): WorkerSettings
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration || !$configuration->has('outbox.worker')) {
            throw new InvalidOutboxConfigurationException('Configuration must contain outbox.worker settings.');
        }
        $worker = $configuration->get('outbox.worker');
        if (!is_array($worker)
            || !is_int($worker['lease_seconds'] ?? null)
            || !is_int($worker['idle_delay_seconds'] ?? null)
        ) {
            throw new InvalidOutboxConfigurationException('Outbox worker durations must be integer seconds.');
        }

        return new WorkerSettings($worker['lease_seconds'], $worker['idle_delay_seconds']);
    }
}
