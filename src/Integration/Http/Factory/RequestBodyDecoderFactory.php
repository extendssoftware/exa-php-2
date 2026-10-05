<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRequestBodyDecoderException;
use ExtendsSoftware\ExaPHP\Http\RequestBody\ContentTypeRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\RequestBody\JsonRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

use function array_key_exists;
use function array_map;
use function is_array;
use function is_int;
use function is_string;

/**
 * Creates request body decoders from HTTP application configuration.
 */
final readonly class RequestBodyDecoderFactory
{
    /**
     * Resolves decoders registered under http.request.decoders.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return ContentTypeRequestBodyDecoder The configured content-type dispatcher.
     *
     * @throws InvalidHttpConfigurationException When sections or identifiers are malformed.
     * @throws InvalidRequestBodyDecoderException When resolved decoder registrations are invalid.
     * @throws ServiceLocatorException When required services cannot be resolved.
     */
    public function create(ServiceLocator $services): ContentTypeRequestBodyDecoder
    {
        $request = $this->configuration($services);
        $registrations = array_key_exists('decoders', $request)
            ? $request['decoders'] : ['application/json' => JsonRequestBodyDecoder::class];
        if (!is_array($registrations)) {
            throw new InvalidHttpConfigurationException('Configuration "http.request.decoders" must be a map.');
        }
        foreach ($registrations as $type => $id) {
            if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
                throw new InvalidHttpConfigurationException('Request decoders must map media types to service IDs.');
            }
        }

        return new ContentTypeRequestBodyDecoder(array_map($services->get(...), $registrations));
    }

    /**
     * Creates the JSON decoder with http.request.json.maxBytes, defaulting to one MiB.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return JsonRequestBodyDecoder The bounded JSON decoder.
     *
     * @throws InvalidHttpConfigurationException When configuration or the byte limit is invalid.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    public function createJson(ServiceLocator $services): JsonRequestBodyDecoder
    {
        $request = $this->configuration($services);
        $json = array_key_exists('json', $request) ? $request['json'] : [];
        if (!is_array($json)) {
            throw new InvalidHttpConfigurationException('Configuration "http.request.json" must be an array.');
        }
        $limit = array_key_exists('maxBytes', $json) ? $json['maxBytes'] : 1048576;
        if (!is_int($limit) || $limit < 0) {
            throw new InvalidHttpConfigurationException('JSON maxBytes must be a non-negative integer.');
        }

        return new JsonRequestBodyDecoder($limit);
    }

    /**
     * Reads and validates the HTTP request configuration section.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return array<array-key, mixed> The request configuration.
     *
     * @throws InvalidHttpConfigurationException When configuration sections or the service are invalid.
     * @throws ServiceLocatorException When configuration cannot be resolved.
     */
    private function configuration(ServiceLocator $services): array
    {
        $configuration = $services->get(Configuration::class);
        if (!$configuration instanceof Configuration) {
            throw new InvalidHttpConfigurationException('The configuration service must be a Configuration instance.');
        }
        $http = $configuration->has('http') ? $configuration->get('http') : [];
        if (!is_array($http)) {
            throw new InvalidHttpConfigurationException('Configuration section "http" must be an array.');
        }
        $request = array_key_exists('request', $http) ? $http['request'] : [];
        if (!is_array($request)) {
            throw new InvalidHttpConfigurationException('Configuration section "http.request" must be an array.');
        }

        return $request;
    }
}
