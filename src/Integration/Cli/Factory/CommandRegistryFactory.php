<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Cli\CliException;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Routing\RegisteredCommands;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;

/**
 * Creates CLI commands from named configuration registrations.
 */
final readonly class CommandRegistryFactory
{
    /**
     * Creates the service from cli.commands in configuration order.
     *
     * Missing sections produce empty registrations. Names identify configuration entries only.
     * Command lookup never resolves handler services.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and CLI services.
     *
     * @return RegisteredCommands The configured service.
     *
     * @throws InvalidCliConfigurationException When configuration or collaborator types are invalid.
     * @throws ServiceLocatorException When a required service cannot be resolved.
     * @throws CliException When CLI registration validation fails.
     */
    public function create(ServiceLocator $serviceLocator): RegisteredCommands
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidCliConfigurationException('The configuration service must be a Configuration instance.');
        }
        $cli = $configuration->has('cli') ? $configuration->get('cli') : [];
        if (!is_array($cli)) {
            throw new InvalidCliConfigurationException('Configuration section "cli" must be an array.');
        }
        $registrations = array_key_exists('commands', $cli) ? $cli['commands'] : [];
        if (!is_array($registrations)) {
            throw new InvalidCliConfigurationException('Configuration section "cli.commands" must be an array.');
        }
        foreach ($registrations as $name => $value) {
            if (!is_string($name) || $name === '' || !$value instanceof CommandDefinition) {
                throw new InvalidCliConfigurationException(
                    'CLI commands must map non-empty names to CommandDefinition values.',
                );
            }
        }

        return new RegisteredCommands(array_values($registrations));
    }
}
