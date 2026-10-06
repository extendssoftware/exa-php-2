<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\Exception\InvalidOutboxMessageException;

use function json_validate;

/**
 * Carries an immutable outgoing message with a portable JSON payload.
 */
final readonly class OutboxMessage
{
    /**
     * Creates a message with caller-supplied identity and creation time.
     *
     * Identifiers, message types, JSON text, and timestamp timezones are preserved without normalization.
     * Payloads may contain any valid JSON value within the standard validation depth of 512.
     *
     * @param non-empty-string $id The stable message identifier used across delivery attempts.
     * @param non-empty-string $type The application-defined message type, including its schema version if needed.
     * @param string $payload The UTF-8 JSON payload interpreted according to the message type.
     * @param DateTimeImmutable $createdAt The time the message was created.
     *
     * @throws InvalidOutboxMessageException When the identifier or type is empty, or the payload is invalid JSON.
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $payload,
        public DateTimeImmutable $createdAt,
    ) {
        if ($id === '') {
            throw new InvalidOutboxMessageException('Outbox message identifiers must not be empty.');
        }

        if ($type === '') {
            throw new InvalidOutboxMessageException('Outbox message types must not be empty.');
        }

        if (!json_validate($payload)) {
            throw new InvalidOutboxMessageException('Outbox message payloads must be valid JSON within depth 512.');
        }
    }
}
