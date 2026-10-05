<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Factory;

use ExtendsSoftware\ExaPHP\Cli\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\InputParser;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates the CLI dispatcher from registered collaborators.
 */
final readonly class CommandDispatcherFactory
{
    /**
     * Resolves and validates command dispatch collaborators without resolving handlers.
     *
     * @param ServiceLocator $serviceLocator The application service locator.
     *
     * @return CommandDispatcher The configured dispatcher.
     *
     * @throws InvalidCliConfigurationException When a collaborator has an incompatible type.
     * @throws ServiceLocatorException When a collaborator cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): CommandDispatcher
    {
        $registry = $serviceLocator->get(CommandRegistry::class);
        $parser = $serviceLocator->get(InputParser::class);
        $resolver = $serviceLocator->get(HandlerResolver::class);
        if (!$registry instanceof CommandRegistry || !$parser instanceof InputParser
            || !$resolver instanceof HandlerResolver) {
            throw new InvalidCliConfigurationException('CLI dispatcher services must implement their contracts.');
        }

        return new CommandDispatcher($registry, $parser, $resolver);
    }
}
