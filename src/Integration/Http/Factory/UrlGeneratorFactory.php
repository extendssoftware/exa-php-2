<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Http\Routing\RouteCollection;
use ExtendsSoftware\ExaPHP\Http\Routing\RouteUrlGenerator;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates the UrlGenerator service from the shared route definitions.
 */
final readonly class UrlGeneratorFactory
{
    /**
     * Resolves the shared route collection without resolving handlers.
     *
     * @param ServiceLocator $serviceLocator The application services.
     *
     * @return RouteUrlGenerator The configured service.
     *
     * @throws InvalidHttpConfigurationException When the collection service has an incompatible type.
     * @throws ServiceLocatorException When the collection cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): RouteUrlGenerator
    {
        $routes = $serviceLocator->get(RouteCollection::class);
        if (!$routes instanceof RouteCollection) {
            throw new InvalidHttpConfigurationException('The route collection service must be a RouteCollection.');
        }

        return new RouteUrlGenerator($routes);
    }
}
