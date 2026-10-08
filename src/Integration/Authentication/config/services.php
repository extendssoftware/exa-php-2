<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\AuthenticationMiddleware;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\AuthenticationResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\BearerAuthenticationResponseFactory;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\BearerTokenCredentialsExtractor;
use ExtendsSoftware\ExaPHP\Integration\Authentication\Http\RequestCredentialsExtractor;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        RequestCredentialsExtractor::class => new InvokableDefinition(BearerTokenCredentialsExtractor::class),
        AuthenticationResponseFactory::class => new InvokableDefinition(BearerAuthenticationResponseFactory::class),
        AuthenticationMiddleware::class => new ReflectionDefinition(AuthenticationMiddleware::class),
    ],
];
