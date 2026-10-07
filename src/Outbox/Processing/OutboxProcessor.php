<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing;

use DateInterval;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\Delivery\MessageDelivery;
use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\Exception\RetryTimestampException;
use ExtendsSoftware\ExaPHP\Outbox\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Outbox\Retry\RetryPolicy;

/**
 * Coordinates one claimed message's delivery and processing outcome per invocation.
 */
final readonly class OutboxProcessor
{
    /**
     * Creates a processor with explicit delivery, retry, and time dependencies.
     *
     * @param OutboxStore $store The store managing claims and outcomes.
     * @param MessageDelivery $delivery The message destination.
     * @param RetryPolicy $retryPolicy The policy for failed deliveries.
     * @param Clock $clock The time source used to schedule retries after delivery fails.
     */
    public function __construct(
        private OutboxStore $store,
        private MessageDelivery $delivery,
        private RetryPolicy $retryPolicy,
        private Clock $clock,
    ) {
    }

    /**
     * Claims at most one message, attempts delivery, and records its outcome.
     *
     * Only MessageDeliveryException from delivery is evaluated for retry. Other delivery exceptions and errors
     * propagate unchanged. Store and policy failures propagate without attempting another outcome. Completion is
     * performed after delivery and its failures are never treated as delivery failures. No transaction is started.
     * A failure before recording an outcome leaves recovery to claim expiry.
     *
     * @param DateInterval $leaseDuration The ownership duration passed to the store.
     *
     * @return bool Whether a message was claimed and its completion, retry, or terminal failure was recorded.
     *
     * @throws OutboxException When claiming, deciding a retry, or recording an outcome fails.
     * @throws RetryTimestampException When the retry clock cannot provide the current time.
     * @throws InvalidRetryPolicyException When a retry policy returns a negative delay.
     */
    public function process(DateInterval $leaseDuration): bool
    {
        $claim = $this->store->claim($leaseDuration);
        if ($claim === null) {
            return false;
        }

        try {
            $this->delivery->deliver($claim->message);
        } catch (MessageDeliveryException $failure) {
            $delay = $this->retryPolicy->delay($claim, $failure);
            if ($delay === null) {
                $this->store->fail($claim);

                return true;
            }

            if ($delay < 0) {
                throw new InvalidRetryPolicyException('Retry policies must return a non-negative delay or null.');
            }

            try {
                $now = $this->clock->now();
            } catch (ClockException $exception) {
                throw new RetryTimestampException('Unable to timestamp the next outbox delivery attempt.', 0, $exception);
            }

            $this->store->retry($claim, $now->add(new DateInterval('PT' . $delay . 'S')));

            return true;
        }

        $this->store->complete($claim);

        return true;
    }
}
