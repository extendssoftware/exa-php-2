<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\OutboxMessage;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\InvalidClaimException;

/**
 * Carries an immutable message and its current processing ownership.
 */
final readonly class ClaimedMessage
{
    /**
     * Creates a claim with adapter-supplied ownership and attempt information.
     *
     * Construction preserves the supplied values and does not acquire or verify ownership in a store.
     * Expiry is evaluated by store operations against the current time determined by the store.
     *
     * @param OutboxMessage $message The outgoing message, unchanged across claims.
     * @param positive-int $attempt The claim number, starting at one and increasing on every subsequent claim.
     * @param non-empty-string $token The opaque ownership token identifying this claim.
     * @param DateTimeImmutable $expiresAt The instant at which ownership expires.
     *
     * @throws InvalidClaimException When the attempt is not positive or the ownership token is empty.
     */
    public function __construct(
        public OutboxMessage $message,
        public int $attempt,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {
        if ($attempt < 1) {
            throw new InvalidClaimException('Outbox claim attempts must be positive.');
        }

        if ($token === '') {
            throw new InvalidClaimException('Outbox claim ownership tokens must not be empty.');
        }
    }
}
