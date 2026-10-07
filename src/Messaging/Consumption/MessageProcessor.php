<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Messaging\Consumption;

use DateInterval;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Exception\RetryTimestampException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\Exception\InvalidRetryPolicyException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\Retry\RetryPolicy;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\SubscriberResolver;
use Throwable;

/**
 * Processes at most one subscriber delivery and records its outcome per invocation.
 */
final readonly class MessageProcessor
{
    /**
     * Creates a processor with explicit consumption, resolution, retry, and time dependencies.
     *
     * @param MessageConsumer $consumer The consumer owning receipt validation and settlement.
     * @param SubscriberResolver $resolver The resolver supplying application subscribers.
     * @param RetryPolicy $retryPolicy The policy deciding outcomes after subscriber failures.
     * @param Clock $clock The time source used to schedule retries after processing fails.
     */
    public function __construct(
        private MessageConsumer $consumer,
        private SubscriberResolver $resolver,
        private RetryPolicy $retryPolicy,
        private Clock $clock,
    ) {
    }

    /**
     * Receives one delivery, invokes its subscriber, and acknowledges, retries, or rejects it.
     *
     * Subscriber exceptions and engine errors are evaluated by the retry policy.
     * Receiving, resolution, policy, and settlement failures propagate without attempting another outcome.
     * Acknowledgement is outside the subscriber failure handler. No transaction is started; recovery of unsettled
     * deliveries belongs to the consumer adapter.
     *
     * @return bool Whether a delivery was received and its outcome recorded; false when none is available.
     *
     * @throws MessagingException When receiving, resolution, retry evaluation, or settlement fails.
     * @throws RetryTimestampException When the clock cannot provide the retry timestamp.
     * @throws InvalidRetryPolicyException When a policy returns a negative delay.
     */
    public function process(): bool
    {
        $delivery = $this->consumer->receive();
        if ($delivery === null) {
            return false;
        }
        $subscriber = $this->resolver->resolve($delivery->subscriberId);
        try {
            $subscriber->handle($delivery->message);
        } catch (Throwable $failure) {
            // Subscriber engine errors intentionally follow the same retry policy as application exceptions.
            $delay = $this->retryPolicy->delay($delivery, $failure);
            if ($delay === null) {
                $this->consumer->reject($delivery);

                return true;
            }
            if ($delay < 0) {
                throw new InvalidRetryPolicyException('Retry policies must return a non-negative delay or null.');
            }
            try {
                $now = $this->clock->now();
            } catch (ClockException $exception) {
                throw new RetryTimestampException('Unable to timestamp the next subscriber attempt.', 0, $exception);
            }
            $this->consumer->retry($delivery, $now->add(new DateInterval('PT' . $delay . 'S')));

            return true;
        }
        $this->consumer->acknowledge($delivery);

        return true;
    }
}
