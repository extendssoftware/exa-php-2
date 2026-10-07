<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing;

use DateInterval;
use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\InvalidClaimException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\LostClaimException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\OutboxStoreException;

/**
 * Claims committed outgoing messages and records processing outcomes.
 *
 * Each operation must be atomic and durable on successful return, independently of the producer's transaction.
 * Callers must invoke these operations outside application transactions. No operation delivers a message.
 * The store determines the current time for each operation and compares timestamps as instants.
 * Persistence and time-source failures must be translated with useful context and their cause preserved as the
 * previous exception.
 */
interface OutboxStore
{
    /**
     * Acquires exclusive ownership of one eligible committed message.
     *
     * Newly committed messages are eligible immediately. Scheduled retries are eligible when their due time is
     * at or before now. Active claims exclude other workers until expiry; expiry at or before now permits reclaiming.
     * Completed and terminally failed messages are never eligible. No selection order is guaranteed.
     * Each acquisition increments the attempt number, starting at one, even if delivery never begins.
     * The store calculates expiry by adding the lease duration to the current time used for acquisition.
     * The returned claim must carry that expiry and a new opaque token never reused for that message.
     * A duration that cannot be applied or does not produce an expiry strictly after that time must fail without
     * acquiring a claim.
     *
     * @param DateInterval $leaseDuration The ownership duration, which must produce a future expiry.
     *
     * @return ClaimedMessage|null The acquired claim, or null when no message is eligible.
     *
     * @throws InvalidClaimException When the lease duration cannot be applied or does not produce a future expiry.
     * @throws OutboxStoreException When persistence or obtaining the current time fails.
     */
    public function claim(DateInterval $leaseDuration): ?ClaimedMessage;

    /**
     * Records successful delivery and releases ownership.
     *
     * The message identifier and token must match the stored active claim, whose expiry must be after now.
     * Missing, expired, superseded, or already released claims must fail without changing message state.
     * The completed message must be excluded from future claims.
     *
     * @param ClaimedMessage $claim The ownership acquired from this store.
     *
     * @return void
     *
     * @throws LostClaimException When the claim no longer owns the message.
     * @throws OutboxStoreException When persistence or obtaining the current time fails.
     */
    public function complete(ClaimedMessage $claim): void;

    /**
     * Schedules another delivery attempt and releases ownership.
     *
     * Ownership validation and rejection must follow the same rules as complete(). The message becomes eligible
     * at the supplied due time; a due time at or before now permits immediate retry. The attempt number is retained
     * until the next claim increments it. The message envelope remains unchanged.
     *
     * @param ClaimedMessage $claim The ownership acquired from this store.
     * @param DateTimeImmutable $availableAt The earliest instant at which the message may be claimed again.
     *
     * @return void
     *
     * @throws LostClaimException When the claim no longer owns the message.
     * @throws OutboxStoreException When persistence or obtaining the current time fails.
     */
    public function retry(ClaimedMessage $claim, DateTimeImmutable $availableAt): void;

    /**
     * Records terminal failure and releases ownership.
     *
     * Ownership validation and rejection must follow the same rules as complete(). The failed message must be
     * excluded from future claims.
     *
     * @param ClaimedMessage $claim The ownership acquired from this store.
     *
     * @return void
     *
     * @throws LostClaimException When the claim no longer owns the message.
     * @throws OutboxStoreException When persistence or obtaining the current time fails.
     */
    public function fail(ClaimedMessage $claim): void;
}
