<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry;

use ExtendsSoftware\ExaPHP\Messaging\Consumption\ReceivedDelivery;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception\InvalidRetryPolicyException;
use Override;
use Throwable;

/**
 * Retries failed subscriber invocations at a fixed interval up to a maximum acquisition count.
 */
final readonly class FixedDelayRetryPolicy implements RetryPolicy
{
    /**
     * Creates a policy with a fixed delay and total attempt limit.
     *
     * @param non-negative-int $delaySeconds The delay in seconds; zero allows immediate retry.
     * @param positive-int $maxAttempts The total allowed acquisitions, including the initial acquisition.
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
     * {@inheritDoc}
     */
    #[Override]
    public function delay(ReceivedDelivery $delivery, Throwable $failure): ?int
    {
        return $delivery->attempt < $this->maxAttempts ? $this->delaySeconds : null;
    }
}
