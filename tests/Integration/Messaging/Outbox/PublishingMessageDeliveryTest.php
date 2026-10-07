<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging\Outbox;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Outbox\PublishingMessageDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Exception\MessagePublishException;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\MessagePublisher;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class PublishingMessageDeliveryTest extends TestCase
{
    public function testPreservesTheCompleteEnvelopeAcrossRepeatedDeliveryAttempts(): void
    {
        $message = new OutboxMessage(
            'message-1', 'example.v1', '{ "value": 1 }', new DateTimeImmutable('2026-10-07T12:34:56.123456+02:00'),
        );
        $publisher = $this->createMock(MessagePublisher::class);
        $publisher->expects($this->exactly(2))->method('publish')->willReturnCallback(
            static function (Message $published) use ($message): void {
                self::assertSame($message->id, $published->id);
                self::assertSame($message->type, $published->type);
                self::assertSame($message->payload, $published->payload);
                self::assertSame($message->createdAt, $published->createdAt);
            },
        );
        $delivery = new PublishingMessageDelivery($publisher);

        $delivery->deliver($message);
        $delivery->deliver($message);
    }

    public function testTranslatesPublishFailureWithOutboxContextAndPreservesTheCause(): void
    {
        $failure = new MessagePublishException('Destination unavailable.', 0, new RuntimeException('Connection lost.'));
        $publisher = $this->createMock(MessagePublisher::class);
        $publisher->expects($this->once())->method('publish')->willThrowException($failure);
        $message = new OutboxMessage('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));

        try {
            new PublishingMessageDelivery($publisher)->deliver($message);
            self::fail('Expected delivery failure.');
        } catch (MessageDeliveryException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringContainsString('message-1', $exception->getMessage());
        }
    }

    #[DataProvider('unexpectedFailures')]
    public function testUnexpectedFailuresPropagateUnchanged(Throwable $failure): void
    {
        $publisher = $this->createStub(MessagePublisher::class);
        $publisher->method('publish')->willThrowException($failure);
        $message = new OutboxMessage('message-1', 'example.v1', '{}', new DateTimeImmutable('2026-10-07'));

        try {
            new PublishingMessageDelivery($publisher)->deliver($message);
            self::fail('Expected unexpected publisher failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable}> */
    public static function unexpectedFailures(): iterable
    {
        yield [new RuntimeException('Unexpected failure.')];
        yield [new TypeError('Invalid adapter.')];
    }
}
