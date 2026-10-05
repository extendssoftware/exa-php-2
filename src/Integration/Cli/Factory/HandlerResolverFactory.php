<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Factory;

use ExtendsSoftware\ExaPHP\Integration\Cli\Resolver\ServiceLocatorHandlerResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/**
 * Creates a lazy CLI handler resolver using the application service locator.
 */
final readonly class HandlerResolverFactory
{
    /**
     * Creates a resolver without resolving any handler services.
     *
     * @param ServiceLocator $serviceLocator The locator supplying handlers on demand.
     *
     * @return ServiceLocatorHandlerResolver The handler resolver.
     */
    public function create(ServiceLocator $serviceLocator): ServiceLocatorHandlerResolver
    {
        return new ServiceLocatorHandlerResolver($serviceLocator);
    }
}
