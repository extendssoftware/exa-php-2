<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Factory\SubscriberResolverFactory;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Factory\SubscriptionRegistryFactory;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Factory\WorkerSettingsFactory;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\ConsumeCommand;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageProcessor;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriptionRegistry;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;

return [
    'services' => [
        SubscriptionRegistry::class => new FactoryDefinition(new SubscriptionRegistryFactory()->create(...)),
        SubscriberResolver::class => new FactoryDefinition(new SubscriberResolverFactory()->create(...)),
        MessageProcessor::class => new ReflectionDefinition(MessageProcessor::class),
        WorkerSettings::class => new FactoryDefinition(new WorkerSettingsFactory()->create(...)),
        ConsumeCommand::class => new ReflectionDefinition(ConsumeCommand::class),
    ],
    'messaging' => [
        'subscriptions' => [],
        'subscribers' => [],
        'worker' => [
            'idle_delay_seconds' => 1,
        ],
    ],
    'cli' => [
        'commands' => [
            'messaging.consume' => new CommandDefinition(
                'messaging:consume',
                ConsumeCommand::class,
                'Process subscriber deliveries until shutdown is requested.',
                options: [
                    new OptionDefinition('once', 'Process at most one delivery and exit without waiting.'),
                ],
            ),
        ],
    ],
];
