<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\InvalidMethodRegistrationException;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_map;
use function is_array;
use function is_string;

/**
 * Creates JSON-RPC method dispatchers from configured handler service identifiers.
 */
final readonly class MethodDispatcherFactory
{
    /**
     * Resolves handlers from `jsonRpc.methods` and creates an independent dispatcher.
     *
     * Missing sections produce empty registrations. All service identifiers are validated before resolution; handlers
     * are then resolved eagerly in configuration order. Integer keys represent numeric method names. The dispatcher
     * validates reserved names and handler contracts. Resolution and registration failures propagate unchanged.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and method handlers.
     *
     * @return RegisteredMethodDispatcher The configured dispatcher.
     *
     * @throws InvalidJsonRpcConfigurationException When configuration or handler IDs are malformed.
     * @throws ServiceLocatorException When configuration or a method handler cannot be resolved.
     * @throws InvalidMethodRegistrationException When the dispatcher rejects a reserved name or handler type.
     */
    public function create(ServiceLocator $serviceLocator): RegisteredMethodDispatcher
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidJsonRpcConfigurationException(
                'The configuration service must be a Configuration instance.',
            );
        }
        $jsonRpc = $configuration->has('jsonRpc') ? $configuration->get('jsonRpc') : [];
        if (!is_array($jsonRpc)) {
            throw new InvalidJsonRpcConfigurationException('Configuration section `jsonRpc` must be an array.');
        }
        $registrations = array_key_exists('methods', $jsonRpc) ? $jsonRpc['methods'] : [];
        if (!is_array($registrations)) {
            throw new InvalidJsonRpcConfigurationException('Configuration section `jsonRpc.methods` must be an array.');
        }
        foreach ($registrations as $serviceId) {
            if (!is_string($serviceId) || $serviceId === '') {
                throw new InvalidJsonRpcConfigurationException(
                    'JSON-RPC method handler IDs must be non-empty strings.',
                );
            }
        }

        return new RegisteredMethodDispatcher(array_map($serviceLocator->get(...), $registrations));
    }
}
