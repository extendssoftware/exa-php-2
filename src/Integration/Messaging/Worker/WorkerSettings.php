<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Worker;

use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidMessagingConfigurationException;

/**
 * Holds immutable worker idle polling duration.
 */
final readonly class WorkerSettings
{
    /**
     * The wait after an empty poll, in seconds.
     *
     * @var positive-int
     */
    public int $idleDelaySeconds;

    /**
     * Creates settings expressed in whole seconds.
     *
     * @param int $idleDelaySeconds The wait after an empty poll.
     *
     * @throws InvalidMessagingConfigurationException When a duration is not positive.
     */
    public function __construct(int $idleDelaySeconds = 1)
    {
        if ($idleDelaySeconds < 1) {
            throw new InvalidMessagingConfigurationException('Messaging idle delay must be positive seconds.');
        }
        $this->idleDelaySeconds = $idleDelaySeconds;
    }
}
