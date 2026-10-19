<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory\JsonRpcServerFactory;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory\MethodDispatcherFactory;
use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcServer;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'services' => [
        JsonRpcServer::class => new FactoryDefinition(new JsonRpcServerFactory()->create(...)),
        MessageDecoder::class => new InvokableDefinition(MessageDecoder::class),
        MessageEncoder::class => new InvokableDefinition(MessageEncoder::class),
        RegisteredMethodDispatcher::class => new FactoryDefinition(new MethodDispatcherFactory()->create(...)),
    ],
];
