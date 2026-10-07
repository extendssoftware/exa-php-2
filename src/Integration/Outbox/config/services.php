<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Factory\WorkerSettingsFactory;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkCommand;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        OutboxProcessor::class => new ReflectionDefinition(OutboxProcessor::class),
        WorkerSettings::class => new FactoryDefinition(new WorkerSettingsFactory()->create(...)),
        WorkCommand::class => new ReflectionDefinition(WorkCommand::class),
    ],
    'outbox' => [
        'worker' => [
            'lease_seconds' => 30,
            'idle_delay_seconds' => 1,
        ],
    ],
    'cli' => [
        'commands' => [
            'outbox.work' => new CommandDefinition(
                'outbox:work',
                WorkCommand::class,
                'Process outgoing messages until shutdown is requested.',
                options: [
                    new OptionDefinition('once', 'Process at most one message and exit without waiting.'),
                ],
            ),
        ],
    ],
];
