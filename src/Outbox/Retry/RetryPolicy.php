<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Retry;

use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Retry\Exception\InvalidRetryPolicyException;

/**
 * Decides whether and when a failed delivery should be retried.
 */
interface RetryPolicy
{
    /**
     * Returns the delay before another attempt, or rejects further attempts.
     *
     * @param ClaimedMessage $claim The current claim, including the message and attempt number.
     * @param MessageDeliveryException $failure The delivery failure being evaluated.
     *
     * @return non-negative-int|null The delay in seconds, or null to record terminal failure.
     *
     * @throws InvalidRetryPolicyException When the policy configuration is invalid.
     */
    public function delay(ClaimedMessage $claim, MessageDeliveryException $failure): ?int;
}
