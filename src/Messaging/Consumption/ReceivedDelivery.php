<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption;

use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\InvalidReceivedDeliveryException;
use ExtendsSoftware\ExaPHP\Messaging\Message;

/**
 * Carries immutable message data and the receipt identifying one subscriber delivery attempt.
 */
final readonly class ReceivedDelivery
{
    /**
     * Creates a received delivery with an adapter-issued receipt.
     *
     * Values are preserved without normalization. The receipt is opaque and must be passed back to the issuing
     * consumer through this value. Construction does not acquire ownership or establish receipt validity.
     *
     * @param Message $message The original published message, preserved across retries.
     * @param non-empty-string $subscriberId The stable subscriber identifier used for resolution and delivery tracking.
     * @param non-empty-string $receipt The opaque identifier for this consumer's specific delivery attempt.
     *
     * @param positive-int $attempt The acquisition number for this message and subscriber, starting at one.
     *
     * @throws InvalidReceivedDeliveryException When an identifier is empty or the attempt is not positive.
     */
    public function __construct(
        public Message $message,
        public string $subscriberId,
        public string $receipt,
        public int $attempt,
    ) {
        if ($attempt < 1) {
            throw new InvalidReceivedDeliveryException('Received delivery attempts must be positive.');
        }
        if ($subscriberId === '') {
            throw new InvalidReceivedDeliveryException('Received delivery subscriber identifiers must not be empty.');
        }
        if ($receipt === '') {
            throw new InvalidReceivedDeliveryException('Received deliveries require a non-empty receipt.');
        }
    }
}
