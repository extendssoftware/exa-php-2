<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidMessagingConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function is_array;
use function is_int;

/**
 * Reads messaging worker durations from application configuration.
 */
final readonly class WorkerSettingsFactory
{
    /**
     * Creates validated worker settings from messaging.worker configuration.
     *
     * @param ServiceLocator $serviceLocator The locator providing application configuration.
     *
     * @return WorkerSettings The configured worker settings.
     *
     * @throws InvalidMessagingConfigurationException When settings are missing or have invalid types or values.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): WorkerSettings
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration || !$configuration->has('messaging.worker')) {
            throw new InvalidMessagingConfigurationException('Configuration must contain messaging.worker settings.');
        }
        $worker = $configuration->get('messaging.worker');
        if (!is_array($worker) || !is_int($worker['idle_delay_seconds'] ?? null)) {
            throw new InvalidMessagingConfigurationException('Messaging worker durations must be integer seconds.');
        }

        return new WorkerSettings($worker['idle_delay_seconds']);
    }
}
