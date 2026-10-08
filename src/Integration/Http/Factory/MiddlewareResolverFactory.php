<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Integration\Http\Resolver\ServiceLocatorMiddlewareResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/**
 * Creates a lazy HTTP middleware resolver using the application service locator.
 */
final readonly class MiddlewareResolverFactory
{
    /**
     * Creates a resolver without resolving any middleware services.
     *
     * @param ServiceLocator $serviceLocator The locator supplying middleware on demand.
     *
     * @return ServiceLocatorMiddlewareResolver The middleware resolver.
     */
    public function create(ServiceLocator $serviceLocator): ServiceLocatorMiddlewareResolver
    {
        return new ServiceLocatorMiddlewareResolver($serviceLocator);
    }
}
