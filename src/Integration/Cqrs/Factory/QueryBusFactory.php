<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Cqrs\Query\SynchronousQueryBus;
use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Exception\InvalidCqrsConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Creates synchronous query buses from configured handler service identifiers.
 */
final readonly class QueryBusFactory
{
    /**
     * Resolves configured handlers and creates an independent bus.
     *
     * Missing cqrs or queries sections produce an empty bus. Handler services are resolved eagerly.
     * The bus validates message classes and resolved handler contracts.
     * Resolution and CQRS failures propagate unchanged.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and handler services.
     *
     * @return SynchronousQueryBus The configured bus.
     *
     * @throws InvalidCqrsConfigurationException When configuration or handler service identifiers are invalid.
     * @throws ServiceLocatorException When configuration or a handler service cannot be resolved.
     * @throws CqrsException When the bus rejects a registration.
     */
    public function create(ServiceLocator $serviceLocator): SynchronousQueryBus
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidCqrsConfigurationException('The configuration service must be a Configuration instance.');
        }

        $cqrs = $configuration->has('cqrs') ? $configuration->get('cqrs') : [];
        if (!is_array($cqrs)) {
            throw new InvalidCqrsConfigurationException('Configuration section "cqrs" must be an array.');
        }

        $registrations = array_key_exists('queries', $cqrs) ? $cqrs['queries'] : [];
        if (!is_array($registrations)) {
            throw new InvalidCqrsConfigurationException('Configuration section "cqrs.queries" must be an array.');
        }

        foreach ($registrations as $messageClass => $serviceId) {
            if (!is_string($messageClass) || $messageClass === '' || !is_string($serviceId) || $serviceId === '') {
                throw new InvalidCqrsConfigurationException(
                    sprintf(
                        'Registration "%s" in "cqrs.queries" must map a class name to a non-empty service ID.',
                        $messageClass,
                    ),
                );
            }
        }

        $handlers = [];
        foreach ($registrations as $messageClass => $serviceId) {
            $handlers[$messageClass] = $serviceLocator->get($serviceId);
        }

        return new SynchronousQueryBus($handlers);
    }
}
