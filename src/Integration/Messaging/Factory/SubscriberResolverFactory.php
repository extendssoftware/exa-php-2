<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidMessagingConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidSubscriberMappingException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Resolver\ServiceLocatorSubscriberResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function is_array;

/**
 * Creates lazy subscriber resolution from application service mappings.
 */
final readonly class SubscriberResolverFactory
{
    /**
     * Reads messaging.subscribers without constructing mapped services.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and subscriber services.
     *
     * @return ServiceLocatorSubscriberResolver The resolver using explicit subscriber-to-service mappings.
     *
     * @throws InvalidMessagingConfigurationException When subscriber configuration has an invalid structure.
     * @throws InvalidSubscriberMappingException When a subscriber or service identifier is invalid.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): ServiceLocatorSubscriberResolver
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration || !$configuration->has('messaging.subscribers')) {
            throw new InvalidMessagingConfigurationException('Configuration must contain messaging.subscribers.');
        }
        $subscribers = $configuration->get('messaging.subscribers');
        if (!is_array($subscribers)) {
            throw new InvalidMessagingConfigurationException('Messaging subscribers must be service mappings.');
        }

        return new ServiceLocatorSubscriberResolver($serviceLocator, $subscribers);
    }
}
