<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidConfigurationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ReservedServiceDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\DefinitionServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\AliasServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\FactoryServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InstanceServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InvokableServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\ReflectionServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * Creates application service locators from configuration using the built-in resolvers.
 */
final readonly class ServiceLocatorFactory
{
    /**
     * Creates a locator with configured definitions and the supplied configuration registered as a service.
     *
     * The optional services section must contain ServiceDefinition objects. Configuration::class is reserved.
     * Services are resolved lazily; creation does not execute factories or validate dependency graphs.
     * Each call creates an independent locator without modifying the supplied configuration.
     *
     * @param Configuration $configuration The application configuration.
     *
     * @return ServiceLocator The locator with instance, alias, factory, invokable, and reflection resolvers.
     *
     * @throws InvalidConfigurationException When the services section is not an array.
     * @throws InvalidServiceDefinitionException When a service entry does not implement ServiceDefinition.
     * @throws ReservedServiceDefinitionException When configuration defines the reserved configuration service.
     */
    public function create(Configuration $configuration): ServiceLocator
    {
        $definitions = $configuration->has('services') ? $configuration->get('services') : [];
        if (!is_array($definitions)) {
            throw new InvalidConfigurationException('Configuration section "services" must be an array.');
        }

        if (array_key_exists(Configuration::class, $definitions)) {
            throw new ReservedServiceDefinitionException(
                sprintf('Service "%s" is reserved for the application configuration.', Configuration::class),
            );
        }

        foreach ($definitions as $id => $definition) {
            if (!$definition instanceof ServiceDefinition) {
                throw new InvalidServiceDefinitionException(
                    sprintf('Service "%s" must be a ServiceDefinition, %s given.', $id, get_debug_type($definition)),
                );
            }
        }

        $definitions[Configuration::class] = new InstanceDefinition($configuration);

        return new DefinitionServiceLocator($definitions, [
            new InstanceServiceResolver(),
            new AliasServiceResolver(),
            new FactoryServiceResolver(),
            new InvokableServiceResolver(),
            new ReflectionServiceResolver(),
        ]);
    }
}
