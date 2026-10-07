<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Outbox\Processing;

use DateInterval;
use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\LostClaimException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\OutboxStoreException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\RetryTimestampException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use ExtendsSoftware\ExaPHP\Outbox\Retry\RetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class OutboxProcessorTest extends TestCase
{
    public function testReturnsFalseWithoutDeliveryOrOutcomeWhenNoMessageIsAvailable(): void
    {
        $lease = new DateInterval('PT30S');
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->with($lease)->willReturn(null);
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createMock(MessageDelivery::class);
        $delivery->expects($this->never())->method('deliver');
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->never())->method('delay');
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');

        self::assertFalse(new OutboxProcessor($store, $delivery, $policy, $clock)->process($lease));
    }

    public function testCompletesOnlyAfterDeliveringTheOriginalMessage(): void
    {
        $claim = $this->claim();
        $delivered = false;
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($claim);
        $store->expects($this->once())->method('complete')->with($claim)->willReturnCallback(
            function () use (&$delivered): void {
                self::assertTrue($delivered);
            },
        );
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createMock(MessageDelivery::class);
        $delivery->expects($this->once())->method('deliver')->with($claim->message)->willReturnCallback(
            static function () use (&$delivered): void {
                $delivered = true;
            },
        );
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->never())->method('delay');
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');

        self::assertTrue(new OutboxProcessor($store, $delivery, $policy, $clock)->process(new DateInterval('PT30S')));
    }

    #[DataProvider('retryDelays')]
    public function testSchedulesRetryFromTheClockTimeAfterDeliveryFails(int $delay): void
    {
        $claim = $this->claim();
        $now = new DateTimeImmutable('2026-10-06T12:00:20.123456Z');
        $failure = new MessageDeliveryException('Timed out.');
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($claim);
        $store->expects($this->once())->method('retry')->with($claim, $now->add(new DateInterval('PT' . $delay . 'S')));
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException($failure);
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->once())->method('delay')
            ->with($this->identicalTo($claim), $this->identicalTo($failure))->willReturn($delay);

        $processor = new OutboxProcessor($store, $delivery, $policy, new FrozenClock($now));

        self::assertTrue($processor->process(new DateInterval('PT30S')));
    }

    /** @return iterable<array{int}> */
    public static function retryDelays(): iterable
    {
        yield [60];
        yield [0];
    }

    public function testRecordsTerminalFailureWhenAttemptsAreExhaustedWithoutReadingTheClock(): void
    {
        $claim = $this->claim();
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($claim);
        $store->expects($this->once())->method('fail')->with($claim);
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException(new MessageDeliveryException('Timed out.'));
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');

        $processor = new OutboxProcessor($store, $delivery, new FixedDelayRetryPolicy(60, 1), $clock);

        self::assertTrue($processor->process(new DateInterval('PT30S')));
    }

    #[DataProvider('storeFailures')]
    public function testStoreFailuresPropagateWithoutTryingAnotherOutcome(string $operation, Throwable $failure): void
    {
        $claim = $this->claim();
        $store = $this->createMock(OutboxStore::class);
        if ($operation === 'claim') {
            $store->expects($this->once())->method('claim')->willThrowException($failure);
        } else {
            $store->expects($this->once())->method('claim')->willReturn($claim);
        }
        foreach (['complete', 'retry', 'fail'] as $outcome) {
            if ($outcome === $operation) {
                $store->expects($this->once())->method($outcome)->willThrowException($failure);
            } else {
                $store->expects($this->never())->method($outcome);
            }
        }
        $delivery = $this->createMock(MessageDelivery::class);
        if ($operation === 'claim') {
            $delivery->expects($this->never())->method('deliver');
        } elseif ($operation === 'complete') {
            $delivery->expects($this->once())->method('deliver');
        } else {
            $delivery->expects($this->once())->method('deliver')
                ->willThrowException(new MessageDeliveryException('Timed out.'));
        }
        $policy = new FixedDelayRetryPolicy(60, $operation === 'fail' ? 1 : 3);
        $processor = new OutboxProcessor($store, $delivery, $policy, new FrozenClock($claim->message->createdAt));

        try {
            $processor->process(new DateInterval('PT30S'));
            self::fail('Expected store failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<string, array{string, Throwable}> */
    public static function storeFailures(): iterable
    {
        foreach (['claim', 'complete', 'retry', 'fail'] as $operation) {
            yield $operation . ' persistence failure' => [$operation, new OutboxStoreException('Store unavailable.')];
            if ($operation !== 'claim') {
                yield $operation . ' lost ownership' => [$operation, new LostClaimException('Claim expired.')];
            }
        }
    }

    #[DataProvider('unexpectedDeliveryFailures')]
    public function testUnexpectedDeliveryFailuresPropagateWithoutRecordingAnOutcome(Throwable $failure): void
    {
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($this->claim());
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException($failure);
        $policy = $this->createMock(RetryPolicy::class);
        $policy->expects($this->never())->method('delay');
        $processor = new OutboxProcessor($store, $delivery, $policy, $this->createStub(Clock::class));

        try {
            $processor->process(new DateInterval('PT30S'));
            self::fail('Expected delivery failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function unexpectedDeliveryFailures(): iterable
    {
        yield [new RuntimeException('Unexpected failure.')];
        yield [new TypeError('Invalid delivery implementation.')];
    }

    public function testClockFailurePreservesItsCauseWithoutRecordingAnOutcome(): void
    {
        $failure = new class ('Clock unavailable.') extends RuntimeException implements ClockException {
        };
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($this->claim());
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException(new MessageDeliveryException('Timed out.'));
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willThrowException($failure);
        $processor = new OutboxProcessor($store, $delivery, new FixedDelayRetryPolicy(60, 3), $clock);

        try {
            $processor->process(new DateInterval('PT30S'));
            self::fail('Expected timestamp failure.');
        } catch (RetryTimestampException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testRejectsNegativePolicyDelayWithoutRecordingAnOutcome(): void
    {
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($this->claim());
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException(new MessageDeliveryException('Timed out.'));
        $policy = $this->createStub(RetryPolicy::class);
        $policy->method('delay')->willReturn(-1);
        $processor = new OutboxProcessor($store, $delivery, $policy, $this->createStub(Clock::class));

        $this->expectException(InvalidRetryPolicyException::class);

        $processor->process(new DateInterval('PT30S'));
    }

    public function testPolicyFailurePropagatesWithoutRecordingAnOutcome(): void
    {
        $failure = new InvalidRetryPolicyException('Policy cannot evaluate this attempt.');
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($this->claim());
        $store->expects($this->never())->method('complete');
        $store->expects($this->never())->method('retry');
        $store->expects($this->never())->method('fail');
        $delivery = $this->createStub(MessageDelivery::class);
        $delivery->method('deliver')->willThrowException(new MessageDeliveryException('Timed out.'));
        $policy = $this->createStub(RetryPolicy::class);
        $policy->method('delay')->willThrowException($failure);
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->never())->method('now');
        $processor = new OutboxProcessor($store, $delivery, $policy, $clock);

        try {
            $processor->process(new DateInterval('PT30S'));
            self::fail('Expected policy failure.');
        } catch (InvalidRetryPolicyException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    private function claim(): ClaimedMessage
    {
        $createdAt = new DateTimeImmutable('2026-10-06T12:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{}', $createdAt);

        return new ClaimedMessage($message, 1, 'token-1', $createdAt->add(new DateInterval('PT30S')));
    }
}
