<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use Throwable;

use function sprintf;

/**
 * Constructs fresh service instances from invokable definitions without constructor arguments.
 *
 * An __invoke() method is not required. Loading and construction errors, including engine errors,
 * are translated into component exceptions with their original cause preserved.
 */
final readonly class InvokableServiceResolver implements ServiceResolver
{
    /**
     * Checks whether the definition describes an invokable service.
     *
     * @param ServiceDefinition $definition The service definition.
     *
     * @return bool Whether the definition is supported.
     */
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof InvokableDefinition;
    }

    /**
     * Constructs a new service instance without consulting the locator.
     *
     * @param ServiceDefinition $definition The service definition.
     * @param ServiceLocator $serviceLocator The locator supplied by the caller.
     *
     * @return object The newly constructed service.
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
     * @throws ServiceResolutionException When loading or construction fails.
     */
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object
    {
        if (!$definition instanceof InvokableDefinition) {
            throw new UnsupportedDefinitionException('Expected an InvokableDefinition.');
        }

        try {
            $class = $definition->className;

            return new $class();
        } catch (Throwable $exception) {
            throw new ServiceResolutionException(
                sprintf('Could not construct service class "%s".', $definition->className),
                0,
                $exception,
            );
        }
    }
}
