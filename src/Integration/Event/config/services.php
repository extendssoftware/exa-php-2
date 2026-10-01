<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Event\EventDispatcher;
use ExtendsSoftware\ExaPHP\Integration\Event\Factory\EventDispatcherFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        EventDispatcher::class => new FactoryDefinition(new EventDispatcherFactory()->create(...)),
    ],
];
