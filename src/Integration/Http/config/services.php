<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\DefaultExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ExceptionHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Http\Routing\Router;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\PhpResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\PhpServerRequestFactory;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ExceptionHandlingMiddlewareFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\HandlerResolverFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\MiddlewarePipelineFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RouterFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RoutingRequestHandlerFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => [
        'middleware' => [
            'exceptions' => ExceptionHandlingMiddleware::class,
        ],
    ],
    'services' => [
        Router::class => new FactoryDefinition(new RouterFactory()->create(...)),
        HandlerResolver::class => new FactoryDefinition(new HandlerResolverFactory()->create(...)),
        RoutingRequestHandler::class => new FactoryDefinition(new RoutingRequestHandlerFactory()->create(...)),
        RequestHandler::class => new FactoryDefinition(new MiddlewarePipelineFactory()->create(...)),
        ExceptionResponseFactory::class => new InvokableDefinition(DefaultExceptionResponseFactory::class),
        ExceptionHandlingMiddleware::class => new FactoryDefinition(
            new ExceptionHandlingMiddlewareFactory()->create(...),
        ),
        ServerRequestFactory::class => new InvokableDefinition(PhpServerRequestFactory::class),
        ResponseEmitter::class => new InvokableDefinition(PhpResponseEmitter::class),
    ],
];
