<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Pdo\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Exception\InvalidPdoConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Pdo\Exception\PdoConnectionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;
use PDO;
use PDOException;
use TypeError;
use ValueError;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function trim;

/**
 * Creates PDO connections from application configuration.
 */
final readonly class PdoFactory
{
    /**
     * Creates a connection using the pdo configuration section.
     *
     * Username and password default to null and options default to exception error mode.
     * Explicit error modes must be PDO::ERRMODE_EXCEPTION. Connection details are omitted from wrapper messages.
     *
     * @param ServiceLocator $serviceLocator The locator providing application configuration.
     *
     * @return PDO The new connection.
     *
     * @throws InvalidPdoConfigurationException When configuration has invalid types or values.
     * @throws PdoConnectionException When PDO cannot establish the connection.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): PDO
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidPdoConfigurationException('The configuration service must be a Configuration instance.');
        }

        $settings = $configuration->has('pdo') ? $configuration->get('pdo') : [];
        if (!is_array($settings)) {
            throw new InvalidPdoConfigurationException('Configuration section "pdo" must be an array.');
        }

        $dsn = $settings['dsn'] ?? null;
        if (!is_string($dsn) || trim($dsn) === '') {
            throw new InvalidPdoConfigurationException('Configuration "pdo.dsn" must be a non-empty string.');
        }

        $username = $settings['username'] ?? null;
        $password = $settings['password'] ?? null;
        if (($username !== null && !is_string($username)) || ($password !== null && !is_string($password))) {
            throw new InvalidPdoConfigurationException('PDO username and password must be strings or null.');
        }

        $options = array_key_exists('options', $settings) ? $settings['options'] : [];
        if (!is_array($options)) {
            throw new InvalidPdoConfigurationException('Configuration "pdo.options" must be an array.');
        }
        foreach ($options as $attribute => $value) {
            if (!is_int($attribute)) {
                throw new InvalidPdoConfigurationException('PDO option keys must be integer attribute identifiers.');
            }
        }

        if (
            array_key_exists(PDO::ATTR_ERRMODE, $options)
            && $options[PDO::ATTR_ERRMODE] !== PDO::ERRMODE_EXCEPTION
        ) {
            throw new InvalidPdoConfigurationException('PDO error mode must be PDO::ERRMODE_EXCEPTION.');
        }

        try {
            return new PDO($dsn, $username, $password, $options + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $exception) {
            throw new PdoConnectionException('Unable to establish the configured PDO connection.', 0, $exception);
        } catch (TypeError | ValueError $exception) {
            throw new InvalidPdoConfigurationException('PDO rejected the connection configuration.', 0, $exception);
        }
    }
}
