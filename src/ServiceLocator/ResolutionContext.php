<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator;

use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\CircularDependencyException;
use Throwable;

use function array_pop;
use function implode;
use function in_array;
use function sprintf;

/**
 * Tracks a synchronous service resolution path and rejects circular dependencies.
 *
 * Each independent locator owns its own context. Identifiers are compared within this context only.
 */
final class ResolutionContext
{
    /**
     * Identifiers in the active resolution path.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * Runs a service resolution while tracking its identifier.
     *
     * The identifier is removed after success or failure. Callback exceptions and errors propagate unchanged;
     * the context does not translate them into service locator exceptions.
     *
     * @template T of object
     *
     * @param string $id The service identifier.
     * @param callable(): T $resolve The operation that resolves the service.
     *
     * @return T The resolved service.
     *
     * @throws CircularDependencyException When the identifier is already in the active resolution path.
     * @throws Throwable Any failure from the supplied callback, propagated unchanged.
     */
    public function resolve(string $id, callable $resolve): object
    {
        if (in_array($id, $this->resolving, true)) {
            throw new CircularDependencyException(
                sprintf('Circular service dependency: %s.', implode(' -> ', [...$this->resolving, $id])),
            );
        }

        $this->resolving[] = $id;

        try {
            return $resolve();
        } finally {
            array_pop($this->resolving);
        }
    }
}
