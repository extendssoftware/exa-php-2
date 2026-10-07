<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Consumption;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\DeliverySettlementException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\MessageReceiveException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\RetryTimestampException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\UnavailableDeliveryException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageConsumer;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageProcessor;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\SubscriberResolutionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class MessageProcessorTest extends TestCase
{
    public function testEmptyPollDoesNotResolveOrSettleAnything(): void
    {
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn(null);
        foreach (['acknowledge', 'retry', 'reject'] as $method) {
            $consumer->expects($this->never())->method($method);
        }
        $resolver = $this->createMock(SubscriberResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->never())->method('delay');
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');

        self::assertFalse(new MessageProcessor($consumer, $resolver, $policy, $clock)->process());
    }

    public function testAcknowledgesOnlyAfterHandlingTheOriginalMessage(): void
    {
        $delivery = $this->delivery();
        $handled = false;
        $subscriber = $this->createMock(MessageSubscriber::class);
        $subscriber->expects($this->once())->method('handle')->with($this->identicalTo($delivery->message))
            ->willReturnCallback(static function () use (&$handled): void {
                $handled = true;
            });
        $resolver = $this->createMock(SubscriberResolver::class);
        $resolver->expects($this->once())->method('resolve')->with('subscriber')->willReturn($subscriber);
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($this->once())->method('acknowledge')->with($this->identicalTo($delivery))
            ->willReturnCallback(static function () use (&$handled): void {
                self::assertTrue($handled);
            });
        $consumer->expects($this->never())->method('retry');
        $consumer->expects($this->never())->method('reject');
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->never())->method('delay');
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');

        self::assertTrue(new MessageProcessor($consumer, $resolver, $policy, $clock)->process());
    }

    #[DataProvider('retryDecisions')]
    public function testUsesTheFullDeliveryAndFailureForThePolicy(?int $delay, Throwable $failure): void
    {
        $delivery = $this->delivery();
        $subscriber = $this->createStub(MessageSubscriber::class);
        $subscriber->method('handle')->willThrowException($failure);
        $resolver = $this->createStub(SubscriberResolver::class);
        $resolver->method('resolve')->willReturn($subscriber);
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->once())->method('delay')
            ->with($this->identicalTo($delivery), $this->identicalTo($failure))->willReturn($delay);
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($this->never())->method('acknowledge');
        $now = new DateTimeImmutable('2026-10-07T12:00:20.123456+02:00');
        if ($delay === null) {
            $consumer->expects($this->once())->method('reject')->with($delivery);
            $consumer->expects($this->never())->method('retry');
            $clock = $this->createMock(Clock::class);
            $clock->expects($this->never())->method('now');
        } else {
            $consumer->expects($this->never())->method('reject');
            $availableAt = $now->modify('+' . $delay . ' seconds');
            $consumer->expects($this->once())->method('retry')->with($delivery, $availableAt);
            $clock = new FrozenClock($now);
        }

        self::assertTrue(new MessageProcessor($consumer, $resolver, $policy, $clock)->process());
    }

    /** @return iterable<array{int|null, Throwable}> */
    public static function retryDecisions(): iterable
    {
        foreach ([60, 0, null] as $delay) {
            yield [$delay, new RuntimeException('Temporary application failure.')];
            yield [$delay, new TypeError('Bug in subscriber.')];
        }
    }

    #[DataProvider('failures')]
    public function testFailureBoundariesNeverAttemptAnotherOutcome(string $stage, Throwable $failure): void
    {
        $delivery = $this->delivery();
        $consumer = $this->createMock(MessageConsumer::class);
        $receive = $consumer->expects($this->once())->method('receive');
        if ($stage === 'receive') {
            $receive->willThrowException($failure);
        } else {
            $receive->willReturn($delivery);
        }
        foreach (['acknowledge', 'retry', 'reject'] as $method) {
            if ($stage === $method) {
                $consumer->expects($this->once())->method($method)->willThrowException($failure);
            } else {
                $consumer->expects($this->never())->method($method);
            }
        }
        $subscriber = $this->createMock(MessageSubscriber::class);
        if ($stage === 'receive' || $stage === 'resolve') {
            $subscriber->expects($this->never())->method('handle');

        } elseif ($stage === 'acknowledge') {
            $subscriber->expects($this->once())->method('handle');
        } else {
            $subscriber->expects($this->once())->method('handle')->willThrowException(new RuntimeException('Failed.'));
        }
        $resolver = $this->createMock(SubscriberResolver::class);
        if ($stage === 'receive') {
            $resolver->expects($this->never())->method('resolve');
        } elseif ($stage === 'resolve') {
            $resolver->expects($this->once())->method('resolve')->willThrowException($failure);
        } else {
            $resolver->expects($this->once())->method('resolve')->willReturn($subscriber);
        }
        $policy = $this->createMock(RetryPolicy::class);
        if ($stage === 'policy') {
            $policy->expects($this->once())->method('delay')->willThrowException($failure);
        } elseif ($stage === 'negative') {
            $policy->expects($this->once())->method('delay')->willReturn(-1);
        } elseif ($stage === 'reject') {
            $policy->expects($this->once())->method('delay')->willReturn(null);
        } elseif ($stage === 'retry' || $stage === 'clock' || $stage === 'clock-error') {
            $policy->expects($this->once())->method('delay')->willReturn(60);
        } else {
            $policy->expects($this->never())->method('delay');
        }
        $clock = $this->createMock(Clock::class);
        if ($stage === 'clock' || $stage === 'clock-error') {
            $clock->expects($this->once())->method('now')->willThrowException($failure);
        } elseif ($stage === 'retry') {
            $clock->expects($this->once())->method('now')->willReturn($delivery->message->createdAt);
        } else {
            $clock->expects($this->never())->method('now');
        }
        try {
            new MessageProcessor($consumer, $resolver, $policy, $clock)->process();
            self::fail('Expected processing failure.');
        } catch (Throwable $exception) {
            if ($stage === 'clock') {
                self::assertInstanceOf(RetryTimestampException::class, $exception);
                self::assertSame($failure, $exception->getPrevious());
            } elseif ($stage === 'negative') {
                self::assertInstanceOf(InvalidRetryPolicyException::class, $exception);
            } else {
                self::assertSame($failure, $exception);
            }
        }
    }

    /** @return iterable<string, array{string, Throwable}> */
    public static function failures(): iterable
    {
        yield 'receive failure' => ['receive', new MessageReceiveException('Unavailable.')];
        yield 'resolution failure' => ['resolve', new SubscriberResolutionException('Unknown subscriber.')];
        yield 'receive engine error' => ['receive', new TypeError('Bug in consumer.')];
        yield 'resolution engine error' => ['resolve', new TypeError('Bug in resolver.')];
        yield 'policy engine error' => ['policy', new TypeError('Bug in retry policy.')];
        yield 'clock engine error' => ['clock-error', new TypeError('Bug in clock.')];
        yield 'policy failure' => ['policy', new InvalidRetryPolicyException('Invalid policy.')];
        yield 'negative delay' => ['negative', new InvalidRetryPolicyException('Negative delay.')];
        $clockFailure = new class ('Clock unavailable.') extends RuntimeException implements ClockException {
        };
        yield 'clock failure' => ['clock', $clockFailure];
        foreach (['acknowledge', 'retry', 'reject'] as $stage) {
            yield $stage . ' engine error' => [$stage, new TypeError('Bug in settlement.')];
            yield $stage . ' failure' => [$stage, new DeliverySettlementException('Unknown outcome.')];
            yield $stage . ' lost receipt' => [$stage, new UnavailableDeliveryException('Stale receipt.')];
        }
    }

    #[DataProvider('subscriberFailures')]
    public function testFixedPolicyRejectsAtTheAttemptLimit(Throwable $failure): void
    {
        $delivery = $this->delivery();
        $subscriber = $this->createStub(MessageSubscriber::class);
        $subscriber->method('handle')->willThrowException($failure);
        $resolver = $this->createStub(SubscriberResolver::class);
        $resolver->method('resolve')->willReturn($subscriber);
        $consumer = $this->createMock(MessageConsumer::class);
        $consumer->expects($this->once())->method('receive')->willReturn($delivery);
        $consumer->expects($this->once())->method('reject')->with($delivery);
        $consumer->expects($this->never())->method('retry');
        $consumer->expects($this->never())->method('acknowledge');
        $processor = new MessageProcessor(
            $consumer, $resolver, new FixedDelayRetryPolicy(60, 1), $this->createStub(Clock::class),
        );

        self::assertTrue($processor->process());
    }

    /** @return iterable<array{Throwable}> */
    public static function subscriberFailures(): iterable
    {
        yield [new RuntimeException('Application failure.')];
        yield [new TypeError('Subscriber engine error.')];
    }

    private function delivery(): ReceivedDelivery
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07T12:00:00Z'));

        return new ReceivedDelivery($message, 'subscriber', 'receipt-1', 1);
    }
}
