<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry;

use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception\InvalidRetryPolicyException;
use Throwable;

/**
 * Decides whether and when a failed delivery should be retried.
 */
interface RetryPolicy
{
    /**
     * Returns the delay before another attempt, or rejects further attempts.
     *
     * @param ReceivedDelivery $delivery The received delivery, including the message and attempt number.
     * @param Throwable $failure The subscriber failure being evaluated.
     *
     * @return non-negative-int|null The delay in seconds, or null to record terminal failure.
     *
     * @throws InvalidRetryPolicyException When the policy configuration is invalid.
     */
    public function delay(ReceivedDelivery $delivery, Throwable $failure): ?int;
}
