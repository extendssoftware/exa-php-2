<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Retry;

use ExtendsSoftware\ExaPHP\Outbox\Delivery\Exception\MessageDeliveryException;
use ExtendsSoftware\ExaPHP\Outbox\Processing\ClaimedMessage;
use ExtendsSoftware\ExaPHP\Outbox\Retry\Exception\InvalidRetryPolicyException;
use Override;

/**
 * Retries failed deliveries at a fixed interval up to a maximum claim count.
 */
final readonly class FixedDelayRetryPolicy implements RetryPolicy
{
    /**
     * Creates a policy with a fixed delay and total attempt limit.
     *
     * @param non-negative-int $delaySeconds The delay in seconds; zero allows immediate retry.
     * @param positive-int $maxAttempts The total allowed claims, including the initial claim.
     *
     * @throws InvalidRetryPolicyException When the delay is negative or the attempt limit is not positive.
     */
    public function __construct(private int $delaySeconds, private int $maxAttempts)
    {
        if ($delaySeconds < 0 || $maxAttempts < 1) {
            throw new InvalidRetryPolicyException('Retry delays must be non-negative and attempt limits positive.');
        }
    }

    /**
     * Returns the fixed delay until the attempt limit is reached.
     *
     * All delivery failures receive the same retry decision.
     *
     * @param ClaimedMessage $claim The current claim whose attempt number determines the retry limit.
     * @param MessageDeliveryException $failure The delivery failure being evaluated.
     *
     * @return non-negative-int|null The delay in seconds, or null when the attempt limit has been reached.
     */
    #[Override]
    public function delay(ClaimedMessage $claim, MessageDeliveryException $failure): ?int
    {
        return $claim->attempt < $this->maxAttempts ? $this->delaySeconds : null;
    }
}
