<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging\Worker;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;
use ExtendsSoftware\ExaPHP\Cli\Worker\WorkerControl;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\WorkerRunException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\ConsumeCommand;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Worker\WorkerSettings;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\MessageReceiveException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageProcessor;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class ConsumeCommandTest extends TestCase
{
    #[DataProvider('singlePolls')]
    public function testOncePollsAtMostOneMessageWithoutStartingControl(bool $available): void
    {
        $delivery = $available ? $this->delivery() : null;
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($available ? $this->once() : $this->never())->method('acknowledge');
        $control = $this->createMock(WorkerControl::class);
        $control->expects($this->never())->method('start');
        $control->expects($this->never())->method('wait');
        $control->expects($this->never())->method('finish');

        $command = $this->command($consumer, $control);

        self::assertSame(0, $command->handle(
            new Input('messaging:consume', options: ['once' => true]), $this->createStub(Output::class),
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
        $delivery = $this->delivery();
        $stopped = false;
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->exactly(3))->method('receive')->willReturn($delivery, $delivery, null);
        $consumer->expects($this->exactly(2))->method('acknowledge')->with($delivery);
        $control = $this->createMock(WorkerControl::class);
        $control->expects($this->once())->method('start');
        // Capture by reference so a stop during the wait is visible to the next loop check.
        $control->method('stopRequested')->willReturnCallback(static function () use (&$stopped): bool {
            return $stopped;
        });
        $control->expects($this->once())->method('wait')->with(2)->willReturnCallback(
            static function () use (&$stopped): void {
                $stopped = true;
            },
        );
        $control->expects($this->once())->method('finish');

        self::assertSame(0, $this->command($consumer, $control)->handle(
            new Input('messaging:consume'), $this->createStub(Output::class),
        ));
    }

    public function testStopDuringDeliveryFinishesTheAttemptBeforeStopping(): void
    {
        $delivery = $this->delivery();
        $stopped = false;
        $control = $this->createMock(WorkerControl::class);
        $control->expects($this->once())->method('start');
        $control->method('stopRequested')->willReturnCallback(static function () use (&$stopped): bool {
            return $stopped;
        });
        $control->expects($this->never())->method('wait');
        $control->expects($this->once())->method('finish');
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($this->once())->method('acknowledge')->with($delivery)->willReturnCallback(
            static function () use (&$stopped): void {
                self::assertTrue($stopped);
            },
        );
        $subscriber = $this->createStub(MessageSubscriber::class);
        $subscriber->method('handle')->willReturnCallback(static function () use (&$stopped): void {
            $stopped = true;
        });
        $processor = new MessageProcessor(
            $consumer,
            $this->resolver($subscriber),
            new FixedDelayRetryPolicy(1, 3),
            new FrozenClock($delivery->message->createdAt),
        );
        $command = new ConsumeCommand($processor, new WorkerSettings(), $control);

        self::assertSame(0, $command->handle(new Input('messaging:consume'), $this->createStub(Output::class)));
    }

    #[DataProvider('failures')]
    public function testFailuresStopPollingAndRestoreControl(Throwable $failure): void
    {
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willThrowException($failure);
        $control = $this->createMock(WorkerControl::class);
        $control->expects($this->once())->method('start');
        $control->method('stopRequested')->willReturn(false);
        $control->expects($this->never())->method('wait');
        $control->expects($this->once())->method('finish');
        try {
            $this->command($consumer, $control)->handle(new Input('messaging:consume'), $this->createStub(Output::class));
            self::fail('Expected processing failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function failures(): iterable
    {
        yield [new MessageReceiveException('Database unavailable.')];
        yield [new TypeError('Invalid adapter.')];
    }

    public function testPreservesProcessingAndCleanupFailures(): void
    {
        $failure = new MessageReceiveException('Database unavailable.');
        $cleanup = new WorkerControlException('Signal cleanup failed.');
        $consumer = $this->createStub(MessageConsumer::class);
        $consumer->method('receive')->willThrowException($failure);
        $control = $this->createStub(WorkerControl::class);
        $control->method('stopRequested')->willReturn(false);
        $control->method('finish')->willThrowException($cleanup);
        try {
            $this->command($consumer, $control)->handle(new Input('messaging:consume'), $this->createStub(Output::class));
            self::fail('Expected combined failure.');
        } catch (WorkerRunException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertSame($cleanup, $exception->cleanupFailure);
        }
    }

    public function testStartupFailureDoesNotReceiveAMessage(): void
    {
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->never())->method('receive');
        $control = $this->createMock(WorkerControl::class);
        $control->expects($this->once())->method('start')
            ->willThrowException(new WorkerControlException('PCNTL unavailable.'));
        $control->expects($this->never())->method('finish');
        $this->expectException(WorkerControlException::class);

        $this->command($consumer, $control)->handle(new Input('messaging:consume'), $this->createStub(Output::class));
    }

    private function command(MessageConsumer $consumer, WorkerControl $control): ConsumeCommand
    {
        $processor = new MessageProcessor(
            $consumer,
            $this->resolver($this->createStub(MessageSubscriber::class)),
            new FixedDelayRetryPolicy(60, 3),
            new FrozenClock(new DateTimeImmutable('2026-10-07T12:00:00Z')),
        );

        return new ConsumeCommand($processor, new WorkerSettings(2), $control);
    }

    private function resolver(MessageSubscriber $subscriber): SubscriberResolver
    {
        $resolver = $this->createStub(SubscriberResolver::class);
        $resolver->method('resolve')->willReturn($subscriber);

        return $resolver;
    }

    private function delivery(): ReceivedDelivery
    {
        $time = new DateTimeImmutable('2026-10-07T12:00:00Z');

        return new ReceivedDelivery(new Message('one', 'example.v1', '{}', $time), 'subscriber', 'receipt', 1);
    }
}
