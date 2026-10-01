<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Exception\InvalidCqrsConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Creates synchronous command buses from configured handler service identifiers.
 */
final readonly class CommandBusFactory
{
    /**
     * Resolves configured handlers and creates an independent bus.
     *
     * Missing handler configuration produces an empty bus. Handler and middleware services are resolved eagerly.
     * The bus validates message classes and resolved handler contracts.
     * Resolution and CQRS failures propagate unchanged.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and handler services.
     *
     * @return SynchronousCommandBus The configured bus.
     *
     * @throws InvalidCqrsConfigurationException When configuration or service identifiers are invalid.
     * @throws ServiceLocatorException When configuration or a handler service cannot be resolved.
     * @throws CqrsException When the bus rejects a registration.
     */
    public function create(ServiceLocator $serviceLocator): SynchronousCommandBus
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidCqrsConfigurationException('The configuration service must be a Configuration instance.');
        }

        $cqrs = $configuration->has('cqrs') ? $configuration->get('cqrs') : [];
        if (!is_array($cqrs)) {
            throw new InvalidCqrsConfigurationException('Configuration section "cqrs" must be an array.');
        }

        $command = array_key_exists('command', $cqrs) ? $cqrs['command'] : [];
        if (!is_array($command)) {
            throw new InvalidCqrsConfigurationException('Configuration section "cqrs.command" must be an array.');
        }

        $registrations = array_key_exists('handlers', $command) ? $command['handlers'] : [];
        if (!is_array($registrations)) {
            throw new InvalidCqrsConfigurationException(
                'Configuration section "cqrs.command.handlers" must be an array.',
            );
        }

        foreach ($registrations as $messageClass => $serviceId) {
            if (!is_string($messageClass) || $messageClass === '' || !is_string($serviceId) || $serviceId === '') {
                throw new InvalidCqrsConfigurationException(
                    sprintf(
                        'Registration "%s" in "cqrs.command.handlers" must map a class name to a non-empty service ID.',
                        $messageClass,
                    ),
                );
            }
        }

        $middlewareIds = array_key_exists('middleware', $command) ? $command['middleware'] : [];
        if (!is_array($middlewareIds) || !array_is_list($middlewareIds)) {
            throw new InvalidCqrsConfigurationException('Configuration "cqrs.command.middleware" must be a list.');
        }
        foreach ($middlewareIds as $serviceId) {
            if (!is_string($serviceId) || $serviceId === '') {
                throw new InvalidCqrsConfigurationException(
                    'Command middleware service IDs must be non-empty strings.',
                );
            }
        }

        $handlers = array_map($serviceLocator->get(...), $registrations);
        $middleware = array_map($serviceLocator->get(...), $middlewareIds);

        return new SynchronousCommandBus($handlers, $middleware);
    }
}
