<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Outbox\Worker;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;
use ExtendsSoftware\ExaPHP\Cli\Worker\WorkerControl;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Exception\WorkerRunException;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkCommand;
use ExtendsSoftware\ExaPHP\Integration\Outbox\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\OutboxStoreException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class WorkCommandTest extends TestCase
{
    #[DataProvider('singlePolls')]
    public function testOncePollsAtMostOneMessageWithoutStartingControl(bool $available): void
    {
        $claim = $available ? $this->claim() : null;
        $store = $this->createMock(OutboxStore::class);
        $store->expects(self::once())->method('claim')->willReturn($claim);
        $store->expects($available ? self::once() : self::never())->method('complete');
        $control = $this->createMock(WorkerControl::class);
        $control->expects(self::never())->method('start');
        $control->expects(self::never())->method('wait');
        $control->expects(self::never())->method('finish');

        $command = $this->command($store, $control);

        self::assertSame(0, $command->handle(
            new Input('outbox:work', options: ['once' => true]),
            self::createStub(Output::class),
        ));
    }

    /** @return iterable<array{bool}> */
    public static function singlePolls(): iterable
    {
        yield [false];
        yield [true];
    }

    public function testContinuesAfterMessagesAndWaitsOnlyAfterAnEmptyPoll(): void
    {
        $claim = $this->claim();
        $stopped = false;
        $store = $this->createMock(OutboxStore::class);
        $store->expects(self::exactly(3))->method('claim')->willReturn($claim, $claim, null);
        $store->expects(self::exactly(2))->method('complete')->with($claim);
        $control = $this->createMock(WorkerControl::class);
        $control->expects(self::once())->method('start');
        // Capture by reference so a stop during the wait is visible to the next loop check.
        $control->method('stopRequested')->willReturnCallback(static function () use (&$stopped): bool {
            return $stopped;
        });
        $control->expects(self::once())->method('wait')->with(2)->willReturnCallback(
            static function () use (&$stopped): void {
                $stopped = true;
            },
        );
        $control->expects(self::once())->method('finish');

        self::assertSame(0, $this->command($store, $control)->handle(
            new Input('outbox:work'),
            self::createStub(Output::class),
        ));
    }

    public function testStopDuringDeliveryFinishesTheAttemptBeforeStopping(): void
    {
        $claim = $this->claim();
        $stopped = false;
        $control = $this->createMock(WorkerControl::class);
        $control->expects(self::once())->method('start');
        $control->method('stopRequested')->willReturnCallback(static function () use (&$stopped): bool {
            return $stopped;
        });
        $control->expects(self::never())->method('wait');
        $control->expects(self::once())->method('finish');
        $store = $this->createMock(OutboxStore::class);
        $store->expects(self::once())->method('claim')->willReturn($claim);
        $store->expects(self::once())->method('complete')->with($claim)->willReturnCallback(
            static function () use (&$stopped): void {
                self::assertTrue($stopped);
            },
        );
        $delivery = self::createStub(MessageDelivery::class);
        $delivery->method('deliver')->willReturnCallback(static function () use (&$stopped): void {
            $stopped = true;
        });
        $processor = new OutboxProcessor(
            $store,
            $delivery,
            new FixedDelayRetryPolicy(1, 3),
            new FrozenClock($claim->message->createdAt),
        );
        $command = new WorkCommand($processor, new WorkerSettings(), $control);

        self::assertSame(0, $command->handle(new Input('outbox:work'), self::createStub(Output::class)));
    }

    #[DataProvider('failures')]
    public function testFailuresStopPollingAndRestoreControl(Throwable $failure): void
    {
        $store = $this->createMock(OutboxStore::class);
        $store->expects(self::once())->method('claim')->willThrowException($failure);
        $control = $this->createMock(WorkerControl::class);
        $control->expects(self::once())->method('start');
        $control->method('stopRequested')->willReturn(false);
        $control->expects(self::never())->method('wait');
        $control->expects(self::once())->method('finish');
        try {
            $this->command($store, $control)->handle(new Input('outbox:work'), self::createStub(Output::class));
            self::fail('Expected processing failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function failures(): iterable
    {
        yield [new OutboxStoreException('Database unavailable.')];
        yield [new TypeError('Invalid adapter.')];
    }

    public function testPreservesProcessingAndCleanupFailures(): void
    {
        $failure = new OutboxStoreException('Database unavailable.');
        $cleanup = new WorkerControlException('Signal cleanup failed.');
        $store = self::createStub(OutboxStore::class);
        $store->method('claim')->willThrowException($failure);
        $control = self::createStub(WorkerControl::class);
        $control->method('stopRequested')->willReturn(false);
        $control->method('finish')->willThrowException($cleanup);
        try {
            $this->command($store, $control)->handle(new Input('outbox:work'), self::createStub(Output::class));
            self::fail('Expected combined failure.');
        } catch (WorkerRunException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertSame($cleanup, $exception->cleanupFailure);
        }
    }

    public function testStartupFailureDoesNotClaimAMessage(): void
    {
        $store = $this->createMock(OutboxStore::class);
        $store->expects(self::never())->method('claim');
        $control = $this->createMock(WorkerControl::class);
        $control->expects(self::once())->method('start')
            ->willThrowException(new WorkerControlException('PCNTL unavailable.'));
        $control->expects(self::never())->method('finish');
        $this->expectException(WorkerControlException::class);

        $this->command($store, $control)->handle(new Input('outbox:work'), self::createStub(Output::class));
    }

    private function command(OutboxStore $store, WorkerControl $control): WorkCommand
    {
        $processor = new OutboxProcessor(
            $store,
            self::createStub(MessageDelivery::class),
            new FixedDelayRetryPolicy(60, 3),
            new FrozenClock(new DateTimeImmutable('2026-10-07T12:00:00Z')),
        );

        return new WorkCommand($processor, new WorkerSettings(45, 2), $control);
    }

    private function claim(): ClaimedMessage
    {
        $time = new DateTimeImmutable('2026-10-07T12:00:00Z');

        return new ClaimedMessage(new OutboxMessage('one', 'example.v1', '{}', $time), 1, 'token', $time);
    }
}
