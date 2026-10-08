<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator;

use Override;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\CircularDependencyException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\ServiceResolver;
use Throwable;

use function array_key_exists;
use function sprintf;

/**
 * Resolves registered definitions through ordered resolvers and shares successful service instances.
 *
 * Registrations remain fixed after construction. The first supporting resolver handles each definition.
 * Nested resolutions use this locator, and repeated identifiers in the active resolution path are rejected.
 */
final class DefinitionServiceLocator implements ServiceLocator
{
    /**
     * Successfully resolved services indexed by identifier.
     *
     * @var array<string, object>
     */
    private array $services = [];

    /**
     * Tracks nested resolutions within this locator.
     */
    private readonly ResolutionContext $resolutionContext;

    /**
     * Creates a locator with fixed registrations and resolver order.
     *
     * @param array<string, ServiceDefinition> $definitions Definitions indexed by service identifier.
     * @param list<ServiceResolver> $resolvers Resolvers in selection order.
     */
    public function __construct(private readonly array $definitions, private readonly array $resolvers)
    {
        $this->resolutionContext = new ResolutionContext();
    }

    /**
     * {@inheritDoc}
     *
     * Failed resolutions are not cached and may be retried. Resolved types are not checked against identifiers.
     *
     * @throws ServiceNotFoundException When the identifier is not registered.
     * @throws CircularDependencyException When the identifier is already being resolved.
     * @throws UnsupportedDefinitionException When no resolver supports the registered definition.
     * @throws Throwable When a resolver throws another exception or error, propagated unchanged.
     */
    #[Override]
    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (!$this->has($id)) {
            throw new ServiceNotFoundException(sprintf('Service "%s" is not registered.', $id));
        }

        return $this->services[$id] = $this->resolutionContext->resolve(
            $id,
            fn(): object => $this->resolveDefinition($id),
        );
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * Resolves a registered definition using the first supporting resolver.
     *
     * @param string $id The registered service identifier.
     *
     * @return object The resolved service.
     *
     * @throws UnsupportedDefinitionException When no resolver supports the definition.
     * @throws ServiceLocatorException When a resolver fails.
     */
    private function resolveDefinition(string $id): object
    {
        $definition = $this->definitions[$id];
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($definition)) {
                return $resolver->resolve($definition, $this);
            }
        }

        throw new UnsupportedDefinitionException(
            sprintf('No resolver supports service "%s" (%s).', $id, $definition::class),
        );
    }
}
