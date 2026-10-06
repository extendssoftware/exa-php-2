<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\ServiceLocator;

/**
 * Provides access to object services by identifier.
 */
interface ServiceLocator
{
    /**
     * Resolves a service by its identifier.
     *
     * Class and interface identifiers are expected to resolve to instances of that type for generic type inference.
     *
     * @template T of object
     *
     * @param string|class-string<T> $id The service identifier.
     *
     * @return ($id is class-string<T> ? T : object) The resolved service.
     *
     * @throws ServiceLocatorException When the identifier is unknown or resolution fails.
     */
    public function get(string $id): object;

    /**
     * Checks whether a service identifier is known.
     *
     * A known identifier does not guarantee successful resolution.
     *
     * @param string $id The service identifier.
     *
     * @return bool Whether the identifier is known.
     *
     * @throws ServiceLocatorException When availability cannot be determined.
     */
    public function has(string $id): bool;
}
