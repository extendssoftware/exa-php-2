<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Middleware\MiddlewarePipeline;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

/**
 * Creates HTTP middleware from named configuration registrations.
 */
final readonly class MiddlewarePipelineFactory
{
    /**
     * Creates the service from `http.middleware` in configuration order.
     *
     * Missing sections produce empty registrations. Names identify configuration entries only.
     * Middleware services are resolved eagerly; route handler services remain lazy.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and HTTP services.
     *
     * @return MiddlewarePipeline The configured service.
     *
     * @throws InvalidHttpConfigurationException When configuration or collaborator types are invalid.
     * @throws ServiceLocatorException When a required service cannot be resolved.
     * @throws HttpException When HTTP registration validation fails.
     */
    public function create(ServiceLocator $serviceLocator): MiddlewarePipeline
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidHttpConfigurationException('The configuration service must be a Configuration instance.');
        }
        $http = $configuration->has('http') ? $configuration->get('http') : [];
        if (!is_array($http)) {
            throw new InvalidHttpConfigurationException('Configuration section "http" must be an array.');
        }
        $registrations = array_key_exists('middleware', $http) ? $http['middleware'] : [];
        if (!is_array($registrations)) {
            throw new InvalidHttpConfigurationException('Configuration section "http.middleware" must be an array.');
        }
        foreach ($registrations as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value) || $value === '') {
                throw new InvalidHttpConfigurationException(
                    'HTTP middleware must map non-empty names to non-empty service IDs.',
                );
            }
        }

        $handler = $serviceLocator->get(RoutingRequestHandler::class);
        if (!$handler instanceof RequestHandler) {
            throw new InvalidHttpConfigurationException('The routing handler service must implement RequestHandler.');
        }
        $middleware = array_values(array_map($serviceLocator->get(...), $registrations));

        return new MiddlewarePipeline($handler, $middleware);
    }
}
