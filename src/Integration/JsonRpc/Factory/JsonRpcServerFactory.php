<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory;

use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException;
use ExtendsSoftware\ExaPHP\JsonRpc\DefaultJsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates JSON-RPC servers using registered decoder, dispatcher, and encoder services.
 */
final readonly class JsonRpcServerFactory
{
    /**
     * Resolves collaborators and creates an independent server without executing a JSON-RPC operation.
     *
     * @param ServiceLocator $serviceLocator The locator supplying the message processing services.
     *
     * @return DefaultJsonRpcServer The server using the resolved collaborators.
     *
     * @throws InvalidJsonRpcConfigurationException When a collaborator has an incompatible type.
     * @throws ServiceLocatorException When a collaborator cannot be resolved, propagated unchanged.
     */
    public function create(ServiceLocator $serviceLocator): DefaultJsonRpcServer
    {
        $decoder = $serviceLocator->get(MessageDecoder::class);
        if (!$decoder instanceof MessageDecoder) {
            throw new InvalidJsonRpcConfigurationException('The decoder service must be a MessageDecoder instance.');
        }
        $dispatcher = $serviceLocator->get(RegisteredMethodDispatcher::class);
        if (!$dispatcher instanceof RegisteredMethodDispatcher) {
            throw new InvalidJsonRpcConfigurationException(
                'The dispatcher service must be a RegisteredMethodDispatcher instance.',
            );
        }
        $encoder = $serviceLocator->get(MessageEncoder::class);
        if (!$encoder instanceof MessageEncoder) {
            throw new InvalidJsonRpcConfigurationException('The encoder service must be a MessageEncoder instance.');
        }

        return new DefaultJsonRpcServer($decoder, $dispatcher, $encoder);
    }
}
