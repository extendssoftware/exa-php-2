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
     * @param string $id The service identifier.
     *
     * @return object The resolved service.
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
