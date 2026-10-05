<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use Throwable;
use TypeError;

use function get_debug_type;
use function is_object;
use function sprintf;

/**
 * Resolves factory definitions by invoking factories with the supplied locator.
 *
 * Results are not cached. Component exceptions propagate unchanged. Other factory failures, including engine errors
 * and non-object results, are translated into component exceptions with their original cause preserved.
 */
final readonly class FactoryServiceResolver implements ServiceResolver
{
    /**
     * Checks whether the definition describes a factory service.
     *
     * @param ServiceDefinition $definition The service definition.
     *
     * @return bool Whether the definition is supported.
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof FactoryDefinition;
    }

    /**
     * Invokes the definition's factory with the supplied locator.
     *
     * @param ServiceDefinition $definition The service definition.
     * @param ServiceLocator $serviceLocator The locator available to the factory.
     *
     * @return object The factory's service result.
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
     * @throws ServiceResolutionException When the factory fails or returns a non-object value.
     * @throws ServiceLocatorException When the factory propagates a component failure.
     */
    #[Override]
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object
    {
        if (!$definition instanceof FactoryDefinition) {
            throw new UnsupportedDefinitionException('Expected a FactoryDefinition.');
        }

        try {
            $service = ($definition->factory)($serviceLocator);
            if (!is_object($service)) {
                throw new TypeError(
                    sprintf(
                        'Service factory must return an object, %s returned.',
                        get_debug_type($service),
                    ),
                );
            }

            return $service;
        } catch (ServiceLocatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ServiceResolutionException('Service factory failed.', 0, $exception);
        }
    }
}
