<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidMessagingConfigurationException;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\RegisteredSubscriptions;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_values;
use function is_array;
use function is_string;

/**
 * Creates a subscription registry from named application registrations.
 */
final readonly class SubscriptionRegistryFactory
{
    /**
     * Reads `messaging.subscriptions` without resolving subscriber services.
     *
     * Configuration entry names identify mergeable registrations; Subscription supplies the subscriber identity.
     *
     * @param ServiceLocator $serviceLocator The locator providing application configuration.
     *
     * @return RegisteredSubscriptions The definitions in configuration order.
     *
     * @throws InvalidMessagingConfigurationException When subscription configuration has an invalid structure.
     * @throws MessagingException When subscription registration validation fails.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): RegisteredSubscriptions
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration || !$configuration->has('messaging.subscriptions')) {
            throw new InvalidMessagingConfigurationException('Configuration must contain messaging.subscriptions.');
        }
        $subscriptions = $configuration->get('messaging.subscriptions');
        if (!is_array($subscriptions)) {
            throw new InvalidMessagingConfigurationException('Messaging subscriptions must be named definitions.');
        }
        foreach ($subscriptions as $name => $subscription) {
            if (!is_string($name) || $name === '' || !$subscription instanceof Subscription) {
                throw new InvalidMessagingConfigurationException(
                    'Messaging subscriptions require named Subscription values.',
                );
            }
        }

        return new RegisteredSubscriptions(array_values($subscriptions));
    }
}
