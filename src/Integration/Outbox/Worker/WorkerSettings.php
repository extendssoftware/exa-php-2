<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Outbox\Worker;

use ExtendsSoftware\ExaPHP\Integration\Outbox\Exception\InvalidOutboxConfigurationException;

/**
 * Holds immutable worker lease and idle polling durations.
 */
final readonly class WorkerSettings
{
    /**
     * Creates settings expressed in whole seconds.
     *
     * @param positive-int $leaseSeconds The duration of each claim.
     * @param positive-int $idleDelaySeconds The wait after an empty poll.
     *
     * @throws InvalidOutboxConfigurationException When a duration is not positive.
     */
    public function __construct(public int $leaseSeconds = 30, public int $idleDelaySeconds = 1)
    {
        if ($leaseSeconds < 1 || $idleDelaySeconds < 1) {
            throw new InvalidOutboxConfigurationException('Outbox lease and idle delay must be positive seconds.');
        }
    }
}
