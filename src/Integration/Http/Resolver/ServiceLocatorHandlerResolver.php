<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Resolver;

use Override;
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
     * {@inheritDoc}
     */
    #[Override]
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
