<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidMessagingConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidSubscriberMappingException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\MessagingModule;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\DuplicateSubscriptionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Subscription;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriptionRegistry;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MessagingModuleIntegrationTest extends TestCase
{
    public function testHelpAndDefaultsResolveWithoutConsumerOrPublisher(): void
    {
        $services = new ServiceLocatorFactory()->create($this->configuration());

        self::assertSame('once', $services->get(CommandRegistry::class)->get('messaging:consume')->options[0]->name);
        self::assertSame([], $services->get(SubscriptionRegistry::class)->all());
        self::assertSame(1, $services->get(WorkerSettings::class)->idleDelaySeconds);
        self::assertInstanceOf(SubscriberResolver::class, $services->get(SubscriberResolver::class));
    }

    public function testConsumesOnceUsingMergedSubscriptionsAndLazySubscriberMappings(): void
    {
        $time = new DateTimeImmutable('2026-10-07T12:00:00Z');
        $message = new Message('message-1', 'article.v1', '{}', $time);
        $delivery = new ReceivedDelivery($message, 'indexer', 'receipt', 1);
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($this->once())->method('acknowledge')->with($delivery);
        $subscriber = $this->createMock(MessageSubscriber::class);
        $subscriber->expects($this->once())->method('handle')->with($message);
        $created = 0;
        $definition = new Subscription('indexer', ['article.v1']);
        $configuration = $this->configuration([
            'messaging' => [
                'subscriptions' => ['index' => $definition],
                'subscribers' => ['indexer' => 'handler', 'unused' => 'missing'],
                'worker' => ['idle_delay_seconds' => 2],
            ],
            'services' => [
                MessageConsumer::class => new InstanceDefinition($consumer),
                RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(60, 3)),
                Clock::class => new InstanceDefinition(new FrozenClock($time)),
                'handler' => new FactoryDefinition(static function () use (&$created, $subscriber): MessageSubscriber {
                    ++$created;

                    return $subscriber;
                }),
            ],
        ]);
        $services = new ServiceLocatorFactory()->create($configuration);
        self::assertSame([$definition], $services->get(SubscriptionRegistry::class)->matching($message));
        $resolver = $services->get(SubscriberResolver::class);
        self::assertSame($resolver, $services->get(SubscriberResolver::class));
        self::assertSame(0, $created);
        self::assertSame(2, $services->get(WorkerSettings::class)->idleDelaySeconds);
        self::assertSame(0, $services->get(CommandDispatcher::class)->dispatch(
            'messaging:consume', ['--once'], $this->createStub(Output::class),
        ));
        self::assertSame(1, $created);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param class-string $service
     * @param class-string<Throwable> $cause
     */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(array $overrides, string $service, string $cause): void
    {
        $services = new ServiceLocatorFactory()->create($this->configuration($overrides));
        try {
            $services->get($service);
            self::fail('Expected invalid configuration.');
        } catch (ServiceResolutionException $exception) {
            self::assertInstanceOf($cause, $exception->getPrevious());
        }
    }

    /** @return iterable<array{array<string, mixed>, class-string, class-string<Throwable>}> */
    public static function invalidConfiguration(): iterable
    {
        foreach ([null, 'invalid'] as $value) {
            foreach (['subscriptions' => SubscriptionRegistry::class, 'subscribers' => SubscriberResolver::class,
                'worker' => WorkerSettings::class] as $section => $service) {
                yield [['messaging' => [$section => $value]], $service, InvalidMessagingConfigurationException::class];
            }
        }
        foreach ([0, -1, '1', null] as $delay) {
            yield [['messaging' => ['worker' => ['idle_delay_seconds' => $delay]]], WorkerSettings::class,
                InvalidMessagingConfigurationException::class];
        }
        yield [['messaging' => null], WorkerSettings::class, InvalidMessagingConfigurationException::class];
        yield [['messaging' => ['subscriptions' => ['bad' => 'string']]], SubscriptionRegistry::class,
            InvalidMessagingConfigurationException::class];
        yield [['messaging' => ['subscribers' => ['subscriber' => '']]], SubscriberResolver::class,
            InvalidSubscriberMappingException::class];
        yield [['messaging' => ['subscriptions' => [
            'one' => new Subscription('same', ['one.v1']), 'two' => new Subscription('same', ['two.v1']),
        ]]], SubscriptionRegistry::class, DuplicateSubscriptionException::class];
    }

    /** @param array<string, mixed> $overrides */
    private function configuration(array $overrides = []): Configuration
    {
        $cli = require new CliModule()->configDirectory() . '/services.php';
        $messaging = require new MessagingModule()->configDirectory() . '/services.php';

        return new ConfigurationMerger()->merge(['cli' => $cli, 'messaging' => $messaging], ['app' => $overrides]);
    }
}
