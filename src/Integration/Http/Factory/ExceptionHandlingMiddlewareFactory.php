<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates the configured ExceptionHandlingMiddleware service.
 */
final readonly class ExceptionHandlingMiddlewareFactory
{
    /**
     * Resolves collaborators and creates the HTTP service.
     *
     * @param ServiceLocator $serviceLocator The application's service locator.
     *
     * @return ExceptionHandlingMiddleware The configured service.
     *
     * @throws InvalidHttpConfigurationException When a collaborator has an incompatible type.
     * @throws ServiceLocatorException When a collaborator cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): ExceptionHandlingMiddleware
    {
        $factory = $serviceLocator->get(ExceptionResponseFactory::class);
        if (!$factory instanceof ExceptionResponseFactory) {
            throw new InvalidHttpConfigurationException(
                'The exception response factory service must implement ExceptionResponseFactory.',
            );
        }

        return new ExceptionHandlingMiddleware($factory);
    }
}
