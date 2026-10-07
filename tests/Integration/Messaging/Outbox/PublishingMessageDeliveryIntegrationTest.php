<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging\Outbox;

use DateInterval;
use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Outbox\PublishingMessageDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Exception\MessagePublishException;
use ExtendsSoftware\ExaPHP\Messaging\MessagePublisher;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxProcessor;
use ExtendsSoftware\ExaPHP\Outbox\Processing\OutboxStore;
use ExtendsSoftware\ExaPHP\Outbox\Retry\FixedDelayRetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublishingMessageDeliveryIntegrationTest extends TestCase
{
    #[DataProvider('publishingOutcomes')]
    public function testProcessorRecordsTheOutcomeOfPublishing(bool $accepted): void
    {
        $now = new DateTimeImmutable('2026-10-07T12:00:00Z');
        $message = new OutboxMessage('message-1', 'example.v1', '{}', $now);
        $claim = new ClaimedMessage($message, 1, 'token', $now->add(new DateInterval('PT30S')));
        $publisher = $this->createMock(MessagePublisher::class);
        $publish = $publisher->expects($this->once())->method('publish');
        if (!$accepted) {
            $publish->willThrowException(new MessagePublishException('Acceptance could not be confirmed.'));
        }
        $store = $this->createMock(OutboxStore::class);
        $store->expects($this->once())->method('claim')->willReturn($claim);
        $store->expects($this->never())->method('fail');
        if ($accepted) {
            $store->expects($this->once())->method('complete')->with($claim);
            $store->expects($this->never())->method('retry');
        } else {
            $store->expects($this->never())->method('complete');
            $store->expects($this->once())->method('retry')->with($claim, $now->add(new DateInterval('PT60S')));
        }
        $processor = new OutboxProcessor(
            $store, new PublishingMessageDelivery($publisher), new FixedDelayRetryPolicy(60, 3), new FrozenClock($now),
        );

        self::assertTrue($processor->process(new DateInterval('PT30S')));
    }

    /** @return iterable<string, array{bool}> */
    public static function publishingOutcomes(): iterable
    {
        yield 'accepted and completed' => [true];
        yield 'unconfirmed and retried' => [false];
    }
}
