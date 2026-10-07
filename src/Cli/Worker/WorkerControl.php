<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Worker;

use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;

/**
 * Controls cooperative worker shutdown and interruptible idle waiting.
 */
interface WorkerControl
{
    /**
     * Starts a fresh control session, cleaning up acquired resources if startup fails.
     *
     * @return void
     *
     * @throws WorkerControlException When control cannot be started or is already active.
     */
    public function start(): void;

    /**
     * Reports whether the worker should stop before its next processing attempt.
     *
     * @return bool Whether shutdown has been requested.
     */
    public function stopRequested(): bool;

    /**
     * Waits while idle, returning early when shutdown is requested.
     *
     * @param positive-int $seconds The maximum wait in seconds.
     *
     * @return void
     *
     * @throws WorkerControlException When waiting fails.
     */
    public function wait(int $seconds): void;

    /**
     * Releases control resources after a session, including after a processing failure.
     *
     * @return void
     *
     * @throws WorkerControlException When cleanup fails.
     */
    public function finish(): void;
}
