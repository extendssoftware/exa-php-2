<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Outbox;

use DateInterval;
use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Exception\InvalidOutboxConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Outbox\OutboxModule;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Outbox\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboxModuleIntegrationTest extends TestCase
{
    public function testRegistersCommandWithoutResolvingAdaptersForHelp(): void
    {
        $defaults = require new OutboxModule()->configDirectory() . '/services.php';
        $cli = require new CliModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['cli' => $cli, 'outbox' => $defaults]);
        $services = new ServiceLocatorFactory()->create($configuration);

        $command = $services->get(CommandRegistry::class)->get('outbox:work');

        self::assertSame('once', $command->options[0]->name);
        self::assertSame(30, $services->get(WorkerSettings::class)->leaseSeconds);
        self::assertSame(1, $services->get(WorkerSettings::class)->idleDelaySeconds);
    }

    public function testDispatchesOnceWithConfiguredLeaseAndApplicationAdapters(): void
    {
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->with(new DateInterval('PT45S'))->willReturn(null);
        $defaults = require new OutboxModule()->configDirectory() . '/services.php';
        $cli = require new CliModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['cli' => $cli, 'outbox' => $defaults], [
            'application' => [
                'outbox' => ['worker' => ['lease_seconds' => 45, 'idle_delay_seconds' => 2]],
                'services' => [
                    OutboxStore::class => new InstanceDefinition($store),
                    MessageDelivery::class => new InstanceDefinition($this->createStub(MessageDelivery::class)),
                    RetryPolicy::class => new InstanceDefinition(new FixedDelayRetryPolicy(60, 3)),
                    Clock::class => new InstanceDefinition(new FrozenClock(new DateTimeImmutable('2026-10-07'))),
                ],
            ],
        ]);
        $services = new ServiceLocatorFactory()->create($configuration);
        self::assertSame(0, $services->get(CommandDispatcher::class)->dispatch(
            'outbox:work', ['--once'], $this->createStub(Output::class),
        ));
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidSettings')]
    public function testRejectsInvalidSettings(array $overrides): void
    {
        $defaults = require new OutboxModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge(['outbox' => $defaults], ['app' => $overrides]);
        $services = new ServiceLocatorFactory()->create($configuration);
        try {
            $services->get(WorkerSettings::class);
            self::fail('Expected invalid settings.');
        } catch (ServiceResolutionException $exception) {
            self::assertInstanceOf(InvalidOutboxConfigurationException::class, $exception->getPrevious());
        }
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield [['outbox' => null]];
        yield [['outbox' => ['worker' => null]]];
        yield [['outbox' => ['worker' => ['lease_seconds' => 0]]]];
        yield [['outbox' => ['worker' => ['lease_seconds' => '30']]]];
        yield [['outbox' => ['worker' => ['idle_delay_seconds' => -1]]]];
        yield [['outbox' => ['worker' => ['idle_delay_seconds' => null]]]];
    }
}
