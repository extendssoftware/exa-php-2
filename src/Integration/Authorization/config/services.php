<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationGuard;
use ExtendsSoftware\ExaPHP\Integration\Authorization\Http\AccessDeniedProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        AuthorizationGuard::class => new ReflectionDefinition(AuthorizationGuard::class),
        AccessDeniedProblemDetailsMapper::class => new InvokableDefinition(AccessDeniedProblemDetailsMapper::class),
    ],
    'http' => [
        'exceptionMappers' => [
            'authorization.accessDenied' => AccessDeniedProblemDetailsMapper::class,
        ],
    ],
];
