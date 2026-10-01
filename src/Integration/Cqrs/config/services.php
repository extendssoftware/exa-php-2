<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryBus;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory\CommandBusFactory;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory\QueryBusFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        CommandBus::class => new FactoryDefinition(new CommandBusFactory()->create(...)),
        QueryBus::class => new FactoryDefinition(new QueryBusFactory()->create(...)),
    ],
];
