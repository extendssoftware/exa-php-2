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
     * {@inheritDoc}
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof InstanceDefinition;
    }

    /**
     * {@inheritDoc}
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
