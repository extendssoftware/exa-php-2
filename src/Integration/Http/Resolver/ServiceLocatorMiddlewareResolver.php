<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\Http\Middleware\Exception\MiddlewareResolutionException;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewareResolver;
use ExtendsSoftware\ExaPHP\Http\Middleware\Middleware;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function sprintf;

/**
 * Resolves HTTP middleware through a service locator on demand.
 */
final readonly class ServiceLocatorMiddlewareResolver implements MiddlewareResolver
{
    /**
     * Creates a resolver using the application's services.
     *
     * @param ServiceLocator $serviceLocator The locator supplying middleware services.
     */
    public function __construct(private ServiceLocator $serviceLocator)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function resolve(string $id): Middleware
    {
        try {
            $middleware = $this->serviceLocator->get($id);
        } catch (ServiceLocatorException $exception) {
            throw new MiddlewareResolutionException(
                sprintf('Failed to resolve HTTP middleware "%s".', $id), 0, $exception,
            );
        }
        if (!$middleware instanceof Middleware) {
            throw new MiddlewareResolutionException(sprintf('HTTP middleware "%s" must implement Middleware.', $id));
        }

        return $middleware;
    }
}
