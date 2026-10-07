<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\DeliverySettlementException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\MessageReceiveException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\UnavailableDeliveryException;

/**
 * Receives subscriber deliveries and records their processing outcomes.
 *
 * Settlement must validate the receipt against the outstanding attempt issued by this consumer, including its message
 * and subscriber identity. Unknown, foreign, expired, invalidated, or already settled receipts must fail without
 * changing another delivery. Lost authority must never settle a newer attempt. Successful settlement invalidates the
 * receipt. Expected failures must preserve lower-level causes as previous exceptions. Settlement failures may leave
 * the outcome unknown; callers must not assume another outcome can safely be attempted for the same receipt.
 */
interface MessageConsumer
{
    /**
     * Acquires at most one available delivery without invoking subscriber code.
     *
     * Return null when none is available rather than waiting indefinitely. Each acquisition issues a fresh opaque
     * receipt bound to this consumer and attempt. Never reuse receipts, including after reconnection or recovery.
     * Deliveries to different subscribers settle independently. Repeated receipt of a message is possible; order and
     * exactly-once processing are not guaranteed. Attempt numbers start at one for each message/subscriber pair and
     * increase on every acquisition, including recovery before subscriber processing began. Preserve the count across
     * retries and recovery; do not reset it on reconnect or infer an exact count from a redelivery flag alone.
     *
     * @return ReceivedDelivery|null The message, subscriber, and receipt, or null when no delivery is available.
     *
     * @throws MessageReceiveException When receiving a delivery fails.
     */
    public function receive(): ?ReceivedDelivery;

    /**
     * Acknowledges successful subscriber processing for an outstanding receipt.
     *
     * Successful return means the acknowledgement was submitted successfully; duplicate delivery remains possible.
     * This operation must not invoke subscriber code.
     *
     * @param ReceivedDelivery $delivery The delivery previously issued by this consumer.
     *
     * @return void
     *
     * @throws UnavailableDeliveryException When the delivery's receipt is not valid for an outstanding attempt.
     * @throws DeliverySettlementException When acknowledgement fails or its outcome cannot be determined.
     */
    public function acknowledge(ReceivedDelivery $delivery): void;

    /**
     * Schedules another attempt for this subscriber and settles the current receipt.
     *
     * The retry must not become eligible before the supplied instant. A time at or before the consumer's current
     * time permits immediate retry; actual delivery may occur later. Compare timestamps as instants regardless of
     * timezone. Preserve the message and subscriber identity; a subsequent acquisition must issue a new receipt.
     * Ensure the retry is durably accepted before relinquishing the current delivery. Duplicate delivery remains
     * possible. Unsupported scheduling must fail without relinquishing the delivery. This operation must not sleep
     * or invoke subscriber code.
     *
     * @param ReceivedDelivery $delivery The delivery previously issued by this consumer.
     * @param DateTimeImmutable $availableAt The earliest instant at which the retry may become eligible.
     *
     * @return void
     *
     * @throws UnavailableDeliveryException When the delivery's receipt is not valid for an outstanding attempt.
     * @throws DeliverySettlementException When scheduling is unsupported, fails, or settlement cannot be confirmed.
     */
    public function retry(ReceivedDelivery $delivery, DateTimeImmutable $availableAt): void;

    /**
     * Rejects this subscriber delivery permanently without scheduling another attempt.
     *
     * Successful return means rejection was submitted successfully. Retention follows the destination's configuration;
     * callers must not assume an inspectable failure record. Separately published duplicates may still arrive.
     * This operation must not invoke subscriber code.
     *
     * @param ReceivedDelivery $delivery The delivery previously issued by this consumer.
     *
     * @return void
     *
     * @throws UnavailableDeliveryException When the delivery's receipt is not valid for an outstanding attempt.
     * @throws DeliverySettlementException When rejection fails or its outcome cannot be determined.
     */
    public function reject(ReceivedDelivery $delivery): void;
}
