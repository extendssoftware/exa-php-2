<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/**
 * Returns existing service instances without constructing or cloning them.
 */
final readonly class InstanceServiceResolver implements ServiceResolver
{
    /**
     * Checks whether the definition contains an existing service instance.
     *
     * @param ServiceDefinition $definition The service definition.
     *
     * @return bool Whether the definition is supported.
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof InstanceDefinition;
    }

    /**
     * Returns the exact instance held by the definition without consulting the locator.
     *
     * @param ServiceDefinition $definition The service definition.
     * @param ServiceLocator $serviceLocator The locator supplied by the caller.
     *
     * @return object The existing service instance.
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
     */
    #[Override]
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object
    {
        if (!$definition instanceof InstanceDefinition) {
            throw new UnsupportedDefinitionException('Expected an InstanceDefinition.');
        }

        return $definition->instance;
    }
}
