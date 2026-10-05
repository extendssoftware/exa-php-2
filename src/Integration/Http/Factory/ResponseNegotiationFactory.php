<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\InvalidResponseFactoryException;
use ExtendsSoftware\ExaPHP\Http\Representation\ContentNegotiatingResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_map;
use function is_array;
use function is_string;

/**
 * Builds content negotiation from configured response factory services.
 */
final readonly class ResponseNegotiationFactory
{
    /**
     * Resolves available response factories from http.response configuration.
     *
     * @param ServiceLocator $serviceLocator The application service locator.
     *
     * @return ContentNegotiatingResponseFactory The configured negotiator.
     *
     * @throws InvalidHttpConfigurationException When configuration sections or identifiers are malformed.
     * @throws InvalidResponseFactoryException When resolved registrations or the default media type are invalid.
     * @throws ServiceLocatorException When configuration or factory services cannot be resolved.
     */
    public function create(ServiceLocator $serviceLocator): ContentNegotiatingResponseFactory
    {
        $configuration = $serviceLocator->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidHttpConfigurationException('The configuration service must be a Configuration instance.');
        }
        $http = $configuration->has('http') ? $configuration->get('http') : [];
        if (!is_array($http)) {
            throw new InvalidHttpConfigurationException('Configuration section "http" must be an array.');
        }
        $response = array_key_exists('response', $http) ? $http['response'] : [];
        if (!is_array($response)) {
            throw new InvalidHttpConfigurationException('Configuration section "http.response" must be an array.');
        }
        $registrations = array_key_exists('factories', $response)
            ? $response['factories'] : ['application/json' => JsonResponseFactory::class];
        $default = array_key_exists('default', $response) ? $response['default'] : 'application/json';
        if (!is_array($registrations) || !is_string($default) || $default === '') {
            throw new InvalidHttpConfigurationException(
                'HTTP response factories require a map and a default media type.',
            );
        }
        foreach ($registrations as $type => $id) {
            if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
                throw new InvalidHttpConfigurationException('Response factories must map media types to service IDs.');
            }
        }

        return new ContentNegotiatingResponseFactory(array_map($serviceLocator->get(...), $registrations), $default);
    }
}
