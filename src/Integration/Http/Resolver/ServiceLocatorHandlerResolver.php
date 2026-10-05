<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Resolver;

use ExtendsSoftware\ExaPHP\Http\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function sprintf;

/**
 * Resolves HTTP handlers through a service locator on demand.
 */
final readonly class ServiceLocatorHandlerResolver implements HandlerResolver
{
    /**
     * Creates a resolver using the application's services.
     *
     * @param ServiceLocator $serviceLocator The locator supplying handler services.
     */
    public function __construct(private ServiceLocator $serviceLocator)
    {
    }

    /**
     * Resolves and validates the requested handler service.
     *
     * @param non-empty-string $id The handler service identifier.
     *
     * @return RequestHandler The resolved handler.
     *
     * @throws HandlerResolutionException When service resolution fails or the service is not a request handler.
     */
    public function resolve(string $id): RequestHandler
    {
        try {
            $handler = $this->serviceLocator->get($id);
        } catch (ServiceLocatorException $exception) {
            throw new HandlerResolutionException(sprintf('Failed to resolve HTTP handler "%s".', $id), 0, $exception);
        }
        if (!$handler instanceof RequestHandler) {
            throw new HandlerResolutionException(sprintf('HTTP handler "%s" must implement RequestHandler.', $id));
        }

        return $handler;
    }
}
