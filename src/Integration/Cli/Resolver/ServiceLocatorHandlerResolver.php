<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Resolver;

use Override;
use ExtendsSoftware\ExaPHP\Cli\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Cli\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function sprintf;

/**
 * Resolves CLI handlers through a service locator on demand.
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
    public function resolve(string $id): CommandHandler
    {
        try {
            $handler = $this->serviceLocator->get($id);
        } catch (ServiceLocatorException $exception) {
            throw new HandlerResolutionException(sprintf('Failed to resolve CLI handler "%s".', $id), 0, $exception);
        }
        if (!$handler instanceof CommandHandler) {
            throw new HandlerResolutionException(sprintf('CLI handler "%s" must implement CommandHandler.', $id));
        }

        return $handler;
    }
}
