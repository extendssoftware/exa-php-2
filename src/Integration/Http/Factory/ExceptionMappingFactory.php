<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\MappingExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\RequestBodyExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

/**
 * Composes exception response mapping from named mapper service registrations.
 */
final readonly class ExceptionMappingFactory
{
    /**
     * Creates the service from http.exceptionMappers in configuration order.
     *
     * Missing sections produce empty registrations. Names identify configuration entries only.
     * Mapper services are resolved eagerly. Configured mappers run before decoding and generic error defaults.
     *
     * @param ServiceLocator $serviceLocator The locator providing configuration and HTTP services.
     *
     * @return MappingExceptionResponseFactory The configured service.
     *
     * @throws InvalidHttpConfigurationException When configuration or collaborator types are invalid.
     * @throws ServiceLocatorException When a required service cannot be resolved.
     * @throws HttpException When HTTP registration validation fails.
     */
    public function create(ServiceLocator $serviceLocator): MappingExceptionResponseFactory
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidHttpConfigurationException('The configuration service must be a Configuration instance.');
        }
        $http = $configuration->has('http') ? $configuration->get('http') : [];
        if (!is_array($http)) {
            throw new InvalidHttpConfigurationException('Configuration section "http" must be an array.');
        }
        $registrations = array_key_exists('exceptionMappers', $http) ? $http['exceptionMappers'] : [];
        if (!is_array($registrations)) {
            throw new InvalidHttpConfigurationException(
                'Configuration section "http.exceptionMappers" must be an array.',
            );
        }
        foreach ($registrations as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value) || $value === '') {
                throw new InvalidHttpConfigurationException(
                    'HTTP exception mappers must map non-empty names to non-empty service IDs.',
                );
            }
        }

        $problems = $serviceLocator->get(ProblemDetailsResponseFactory::class);
        if (!$problems instanceof ProblemDetailsResponseFactory) {
            throw new InvalidHttpConfigurationException('Expected a ProblemDetailsResponseFactory service.');
        }
        $mappers = array_values(array_map($serviceLocator->get(...), $registrations));

        return new MappingExceptionResponseFactory(
            $mappers,
            new RequestBodyExceptionResponseFactory(new DefaultExceptionResponseFactory()),
            $problems,
        );
    }
}
