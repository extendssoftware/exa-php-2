<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnresolvableParameterException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

use function sprintf;

/**
 * Constructs fresh services by resolving constructor dependencies through the supplied locator.
 *
 * Named class and interface types identify dependencies, including self and parent relative to the declaring class.
 * Declared defaults apply when no dependency is registered. Required nullable dependencies still require registration.
 * Variadic function receive no arguments. Other by-reference parameters and required parameters without a single class
 * type fail.
 * Component failures propagate unchanged; other loading, reflection, and construction failures are wrapped.
 */
final readonly class ReflectionServiceResolver implements ServiceResolver
{
    /**
     * {@inheritDoc}
     *
     * Checks only the definition type, without loading or inspecting its class.
     */
    #[Override]
    public function supports(ServiceDefinition $definition): bool
    {
        return $definition instanceof ReflectionDefinition;
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedDefinitionException When the definition is unsupported.
     * @throws UnresolvableParameterException When a constructor parameter cannot be supplied.
     * @throws ServiceResolutionException When loading, reflection, or construction fails.
     */
    #[Override]
    public function resolve(ServiceDefinition $definition, ServiceLocator $serviceLocator): object
    {
        if (!$definition instanceof ReflectionDefinition) {
            throw new UnsupportedDefinitionException('Expected a ReflectionDefinition.');
        }

        try {
            $class = new ReflectionClass($definition->className);
            if (!$class->isInstantiable()) {
                throw new ServiceResolutionException(
                    sprintf('Service class "%s" is not instantiable.', $class->getName()),
                );
            }

            $arguments = [];
            foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->isVariadic()) {
                    continue;
                }

                $arguments[] = $this->resolveParameter($parameter, $serviceLocator);
            }

            return $class->newInstanceArgs($arguments);
        } catch (ServiceLocatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ServiceResolutionException(
                sprintf('Could not construct service class "%s" through reflection.', $definition->className),
                0,
                $exception,
            );
        }
    }

    /**
     * Resolves a constructor parameter or uses its declared default.
     *
     * @param ReflectionParameter $parameter The constructor parameter.
     * @param ServiceLocator $serviceLocator The locator providing dependencies.
     *
     * @return mixed The dependency or declared default value.
     *
     * @throws UnresolvableParameterException When the parameter cannot be supplied.
     * @throws Throwable When reflection or dependency lookup fails.
     */
    private function resolveParameter(ReflectionParameter $parameter, ServiceLocator $serviceLocator): mixed
    {
        $declaringClass = $parameter->getDeclaringClass();
        if ($declaringClass === null) {
            throw new UnresolvableParameterException(
                sprintf('Cannot resolve parameter $%s without a declaring class.', $parameter->getName()),
            );
        }

        if (!$parameter->isPassedByReference()) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $parentClass = $declaringClass->getParentClass();
                $id = match ($type->getName()) {
                    'self' => $declaringClass->getName(),
                    'parent' => $parentClass === false ? null : $parentClass->getName(),
                    default => $type->getName(),
                };

                if ($id !== null && (!$parameter->isDefaultValueAvailable() || $serviceLocator->has($id))) {
                    return $serviceLocator->get($id);
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
        }

        throw new UnresolvableParameterException(
            sprintf(
                'Cannot resolve constructor parameter $%s of "%s"; use a factory definition.',
                $parameter->getName(),
                $declaringClass->getName(),
            ),
        );
    }
}
