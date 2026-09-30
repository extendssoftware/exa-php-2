<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Defines support for service definitions and resolves the services they describe.
 */
interface ServiceResolver
{
    /**
     * Checks whether a service definition is supported without constructing the service.
     *
     * @param ServiceDefinition $definition The service definition.
     *
     * @return bool Whether the definition is supported.
     *
     * @throws ServiceLocatorException When support cannot be determined.
     */
    public function supports(ServiceDefinition $definition): bool;

    /**
     * Resolves a service using the supplied locator for dependencies or alias targets.
     *
     * @param ServiceDefinition $definition The service definition.
     * @param ServiceLocator $serviceLocator The locator available for related service resolution.
     *
     * @return object The resolved service.
     *
     * @throws ServiceLocatorException When the definition is unsupported or resolution fails.
     */
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object;
}
