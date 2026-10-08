<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Outbox;

use ExtendsSoftware\ExaPHP\Messaging\Exception\MessagePublishException;
use ExtendsSoftware\ExaPHP\Messaging\Message;
use ExtendsSoftware\ExaPHP\Messaging\MessagePublisher;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use Override;

use function sprintf;

/**
 * Hands outgoing outbox messages to a durable Messaging publisher.
 */
final readonly class PublishingMessageDelivery implements MessageDelivery
{
    /**
     * Creates a delivery adapter using the supplied publisher.
     *
     * @param MessagePublisher $publisher The destination accepting messages for durable distribution.
     */
    public function __construct(private MessagePublisher $publisher)
    {
    }

    /**
     * {@inheritDoc}
     *
     * Preserves the outgoing envelope content. Unexpected exceptions and engine errors propagate unchanged.
     * Delivery does not acknowledge the outbox claim or manage a transaction.
     */
    #[Override]
    public function deliver(OutboxMessage $message): void
    {
        $outgoing = new Message($message->id, $message->type, $message->payload, $message->createdAt);
        try {
            $this->publisher->publish($outgoing);
        } catch (MessagePublishException $exception) {
            throw new MessageDeliveryException(
                sprintf('Unable to hand off outbox message "%s" to the Messaging publisher.', $message->id),
                0,
                $exception,
            );
        }
    }
}
