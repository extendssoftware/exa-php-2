<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Event\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Event\EventException;
use ExtendsSoftware\ExaPHP\Event\SynchronousEventDispatcher;
use ExtendsSoftware\ExaPHP\Integration\Event\Exception\InvalidEventConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Creates synchronous event dispatchers from keyed listener service registrations.
 */
final readonly class EventDispatcherFactory
{
    /**
     * Resolves configured listeners and creates an independent dispatcher.
     *
     * The optional events section maps event classes to named listener service identifiers. Missing configuration
     * produces an empty dispatcher. Listeners are resolved eagerly in configuration order; keys identify registrations.
     * The dispatcher validates event classes and resolved listener contracts. Resolution failures propagate unchanged.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and listener services.
     *
     * @return SynchronousEventDispatcher The configured dispatcher.
     *
     * @throws InvalidEventConfigurationException When configuration or listener registrations are malformed.
     * @throws ServiceLocatorException When configuration or a listener service cannot be resolved.
     * @throws EventException When the dispatcher rejects a registration.
     */
    public function create(ServiceLocator $serviceLocator): SynchronousEventDispatcher
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidEventConfigurationException('The configuration service must be a Configuration instance.');
        }

        $registrations = $configuration->has('events') ? $configuration->get('events') : [];
        if (!is_array($registrations)) {
            throw new InvalidEventConfigurationException('Configuration section "events" must be an array.');
        }

        foreach ($registrations as $eventClass => $listenerServices) {
            if (!is_string($eventClass) || $eventClass === '' || !is_array($listenerServices)) {
                throw new InvalidEventConfigurationException(
                    'Event registrations must map non-empty class names to listener service maps.',
                );
            }

            foreach ($listenerServices as $key => $serviceId) {
                if (!is_string($key) || $key === '' || !is_string($serviceId) || $serviceId === '') {
                    throw new InvalidEventConfigurationException(
                        sprintf(
                            'Listeners for event "%s" must map non-empty names to non-empty service IDs.',
                            $eventClass,
                        ),
                    );
                }
            }
        }

        $listeners = [];
        foreach ($registrations as $eventClass => $listenerServices) {
            $listeners[$eventClass] = array_values(array_map($serviceLocator->get(...), $listenerServices));
        }

        return new SynchronousEventDispatcher($listeners);
    }
}
