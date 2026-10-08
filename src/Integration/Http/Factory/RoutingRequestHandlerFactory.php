<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Routing\Router;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewareResolver;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates the configured RoutingRequestHandler service.
 */
final readonly class RoutingRequestHandlerFactory
{
    /**
     * Resolves collaborators and creates the HTTP service.
     *
     * @param ServiceLocator $serviceLocator The application's service locator.
     *
     * @return RoutingRequestHandler The configured service.
     *
     * @throws InvalidHttpConfigurationException When a collaborator has an incompatible type.
     * @throws ServiceLocatorException When a collaborator cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): RoutingRequestHandler
    {
        $router = $serviceLocator->get(Router::class);
        if (!$router instanceof Router) {
            throw new InvalidHttpConfigurationException('The Router service must implement Router.');
        }
        $resolver = $serviceLocator->get(HandlerResolver::class);
        if (!$resolver instanceof HandlerResolver) {
            throw new InvalidHttpConfigurationException('The HandlerResolver service must implement HandlerResolver.');
        }

        $middleware = $serviceLocator->get(MiddlewareResolver::class);
        if (!$middleware instanceof MiddlewareResolver) {
            throw new InvalidHttpConfigurationException('The MiddlewareResolver service must implement MiddlewareResolver.');
        }

        return new RoutingRequestHandler($router, $resolver, $middleware);
    }
}
