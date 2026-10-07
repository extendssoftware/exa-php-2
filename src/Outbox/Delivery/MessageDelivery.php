<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Delivery;

use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;

/**
 * Delivers outgoing messages to their destination.
 */
interface MessageDelivery
{
    /**
     * Delivers one message and returns when the destination acknowledges acceptance.
     *
     * The stable message identifier must be preserved across delivery attempts. A failure does not guarantee
     * that the destination has not accepted the message. Translate expected delivery failures with useful context
     * and preserve lower-level causes as previous exceptions.
     *
     * @param OutboxMessage $message The message to deliver.
     *
     * @return void
     *
     * @throws MessageDeliveryException When delivery cannot be acknowledged.
     */
    public function deliver(OutboxMessage $message): void;
}
