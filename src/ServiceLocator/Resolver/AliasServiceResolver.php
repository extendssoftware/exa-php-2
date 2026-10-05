<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Delegates alias definitions to target identifiers through the supplied locator.
 *
 * The locator owns instance sharing and circular resolution detection. Target failures propagate unchanged.
 */
final readonly class AliasServiceResolver implements ServiceResolver
{
    /**
     * Checks whether the definition describes an alias.
     *
     * @param ServiceDefinition $definition The service definition.
     *
     * @return bool Whether the definition is supported.
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof AliasDefinition;
    }

    /**
     * Resolves the definition's alias target through the supplied locator.
     *
     * @param ServiceDefinition $definition The service definition.
     * @param ServiceLocator $serviceLocator The locator used to resolve the target.
     *
     * @return object The target service returned by the locator.
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
     * @throws ServiceLocatorException When target resolution fails.
     */
    #[Override]
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object
    {
        if (!$definition instanceof AliasDefinition) {
            throw new UnsupportedDefinitionException('Expected an AliasDefinition.');
        }

        return $serviceLocator->get($definition->target);
    }
}
