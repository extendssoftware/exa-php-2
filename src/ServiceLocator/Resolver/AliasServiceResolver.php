<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/**
 * Delegates alias definitions to target identifiers through the supplied locator.
 *
 * The locator owns instance sharing and circular resolution detection. Target failures propagate unchanged.
 */
final readonly class AliasServiceResolver implements ServiceResolver
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof AliasDefinition;
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
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
