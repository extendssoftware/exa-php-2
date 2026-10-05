<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Routing\Route;
use ExtendsSoftware\ExaPHP\Http\Routing\SimpleRouter;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;

/**
 * Creates HTTP routes from named configuration registrations.
 */
final readonly class RouterFactory
{
    /**
     * Creates the service from http.routes in configuration order.
     *
     * Missing sections produce empty registrations. Names identify configuration entries only.
     * Route matching never resolves handler services.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and HTTP services.
     *
     * @return SimpleRouter The configured service.
     *
     * @throws InvalidHttpConfigurationException When configuration or collaborator types are invalid.
     * @throws ServiceLocatorException When a required service cannot be resolved.
     * @throws HttpException When HTTP registration validation fails.
     */
    public function create(ServiceLocator $serviceLocator): SimpleRouter
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidHttpConfigurationException('The configuration service must be a Configuration instance.');
        }
        $http = $configuration->has('http') ? $configuration->get('http') : [];
        if (!is_array($http)) {
            throw new InvalidHttpConfigurationException('Configuration section "http" must be an array.');
        }
        $registrations = array_key_exists('routes', $http) ? $http['routes'] : [];
        if (!is_array($registrations)) {
            throw new InvalidHttpConfigurationException('Configuration section "http.routes" must be an array.');
        }
        foreach ($registrations as $name => $value) {
            if (!is_string($name) || $name === '' || !$value instanceof Route) {
                throw new InvalidHttpConfigurationException(
                    'HTTP routes must map non-empty names to Route values.',
                );
            }
        }

        return new SimpleRouter(array_values($registrations));
    }
}
