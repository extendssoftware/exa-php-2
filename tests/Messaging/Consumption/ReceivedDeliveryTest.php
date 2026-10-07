<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Messaging\Consumption;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\InvalidReceivedDeliveryException;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReceivedDeliveryTest extends TestCase
{
    #[DataProvider('subscriberIdentifiers')]
    public function testPreservesTheOriginalMessageAndSubscriberIdentity(string $subscriberId): void
    {
        $message = new Message('message-1', 'example.v1', '{ "value": 1 }', new DateTimeImmutable('2026-10-07'));
        $context = new ReceivedDelivery($message, $subscriberId, ' receipt-1 ', 1);

        self::assertSame($message, $context->message);
        self::assertSame($subscriberId, $context->subscriberId);
        self::assertSame(' receipt-1 ', $context->receipt);
        self::assertSame(1, $context->attempt);
    }

    /** @return iterable<array{string}> */
    public static function subscriberIdentifiers(): iterable
    {
        yield ['search-index'];
        yield ['Search-Index'];
        yield [' subscriber '];
        yield ['0'];
    }

    public function testRejectsAnEmptySubscriberIdentifier(): void
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));
        $this->expectException(InvalidReceivedDeliveryException::class);

        new ReceivedDelivery($message, '', 'receipt-1', 1);
    }

    public function testTheSameMessageCanHaveIndependentSubscriberDeliveries(): void
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));
        $first = new ReceivedDelivery($message, 'search-index', 'receipt-1', 1);
        $second = new ReceivedDelivery($message, 'notifications', 'receipt-2', 1);

        self::assertSame($first->message, $second->message);
        self::assertNotSame($first->subscriberId, $second->subscriberId);
        self::assertNotSame($first->receipt, $second->receipt);
    }

    public function testRejectsAnEmptyReceipt(): void
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));
        $this->expectException(InvalidReceivedDeliveryException::class);

        new ReceivedDelivery($message, 'search-index', '', 1);
    }

    public function testRedeliveryCanPreserveTheMessageAndSubscriberWithANewReceipt(): void
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));
        $first = new ReceivedDelivery($message, 'search-index', 'receipt-1', 1);
        $retry = new ReceivedDelivery($message, 'search-index', 'receipt-2', 2);

        self::assertSame($first->message, $retry->message);
        self::assertSame($first->subscriberId, $retry->subscriberId);
        self::assertNotSame($first->receipt, $retry->receipt);
        self::assertSame(2, $retry->attempt);
    }

    #[DataProvider('invalidAttempts')]
    public function testRejectsNonPositiveAttempts(int $attempt): void
    {
        $message = new Message('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));
        $this->expectException(InvalidReceivedDeliveryException::class);

        new ReceivedDelivery($message, 'subscriber', 'receipt', $attempt);
    }

    /** @return iterable<array{int}> */
    public static function invalidAttempts(): iterable
    {
        yield [0];
        yield [-1];
    }
}
