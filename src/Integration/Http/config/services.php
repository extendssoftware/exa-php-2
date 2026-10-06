<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Middleware\ExceptionHandlingMiddleware;
use ExtendsSoftware\ExaPHP\Http\Decoding\JsonRequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Decoding\RequestBodyDecoder;
use ExtendsSoftware\ExaPHP\Http\Representation\ContentNegotiatingResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Representation\JsonResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Routing\Router;
use ExtendsSoftware\ExaPHP\Http\Routing\RoutingRequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\PhpResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\PhpServerRequestFactory;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ExceptionHandlingMiddlewareFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ExceptionMappingFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\HandlerResolverFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\MiddlewarePipelineFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RequestBodyDecoderFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ResponseNegotiationFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RouterFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RoutingRequestHandlerFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'http' => [
        'request' => [
            'decoders' => [
                'application/json' => JsonRequestBodyDecoder::class,
            ],
            'json' => [
                'maxBytes' => 1048576,
            ],
        ],
        'response' => [
            'default' => 'application/json',
            'factories' => [
                'application/json' => JsonResponseFactory::class,
            ],
        ],
        'middleware' => [
            'exceptions' => ExceptionHandlingMiddleware::class,
        ],
    ],
    'services' => [
        ProblemDetailsResponseFactory::class => new InvokableDefinition(ProblemDetailsResponseFactory::class),
        RequestBodyDecoder::class => new FactoryDefinition(new RequestBodyDecoderFactory()->create(...)),
        JsonRequestBodyDecoder::class => new FactoryDefinition(new RequestBodyDecoderFactory()->createJson(...)),
        JsonResponseFactory::class => new InvokableDefinition(JsonResponseFactory::class),
        ContentNegotiatingResponseFactory::class => new FactoryDefinition(
            new ResponseNegotiationFactory()->create(...),
        ),
        Router::class => new FactoryDefinition(new RouterFactory()->create(...)),
        HandlerResolver::class => new FactoryDefinition(new HandlerResolverFactory()->create(...)),
        RoutingRequestHandler::class => new FactoryDefinition(new RoutingRequestHandlerFactory()->create(...)),
        RequestHandler::class => new FactoryDefinition(new MiddlewarePipelineFactory()->create(...)),
        ExceptionResponseFactory::class => new FactoryDefinition(
            new ExceptionMappingFactory()->create(...),
        ),
        ExceptionHandlingMiddleware::class => new FactoryDefinition(
            new ExceptionHandlingMiddlewareFactory()->create(...),
        ),
        ServerRequestFactory::class => new InvokableDefinition(PhpServerRequestFactory::class),
        ResponseEmitter::class => new InvokableDefinition(PhpResponseEmitter::class),
    ],
];
