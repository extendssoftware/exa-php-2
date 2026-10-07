<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Worker;

use ExtendsSoftware\ExaPHP\Cli\Worker\Exception\WorkerControlException;
use Override;

use function function_exists;
use function pcntl_async_signals;
use function pcntl_signal;
use function pcntl_signal_get_handler;
use function usleep;

use const SIGINT;
use const SIGTERM;

/**
 * Handles SIGTERM and SIGINT cooperatively and restores process signal state after the worker exits.
 *
 * Persistent workers require PCNTL and exclusive ownership of these signal handlers during the session.
 * Signal callbacks only request shutdown; they do not interrupt the current processing attempt with an exception.
 */
final class PcntlWorkerControl implements WorkerControl
{
    /**
     * Tracks a cooperative shutdown request from SIGTERM or SIGINT.
     *
     * Reset at session startup and retained after cleanup. A stop request ends polling and idle waiting while
     * allowing the current processing attempt to finish.
     *
     * @var bool Whether a stop signal has requested shutdown.
     */
    private bool $stopped = false;

    /**
     * Retains previous signal handlers for restoration during cleanup.
     *
     * Populated before each handler is replaced, allowing cleanup after partial startup. A non-empty map prevents
     * another session from starting. Cleared after restoration is attempted in finish().
     *
     * @var array<int, callable|int> Previous handlers keyed by signal number, including SIG_DFL and SIG_IGN.
     */
    private array $handlers = [];

    /**
     * Retains the asynchronous signal dispatch setting from before the session.
     *
     * Captured before installing stop handlers and restored by finish() when saved handlers are present.
     *
     * @var bool Whether asynchronous signal dispatch was enabled before startup.
     */
    private bool $previousAsync = false;

    /**
     * Installs stop handlers and enables asynchronous signal dispatch.
     *
     * @return void
     *
     * @throws WorkerControlException When PCNTL is unavailable, control is active, or registration fails.
     */
    #[Override]
    public function start(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            throw new WorkerControlException('Persistent CLI workers require ext-pcntl or another WorkerControl.');
        }
        if ($this->handlers !== []) {
            throw new WorkerControlException('CLI worker signal control is already active.');
        }
        $this->stopped = false;
        $this->previousAsync = pcntl_async_signals();
        foreach ([SIGTERM, SIGINT] as $signal) {
            $this->handlers[$signal] = pcntl_signal_get_handler($signal);
            if (!pcntl_signal($signal, $this->requestStop(...))) {
                $this->finish();
                throw new WorkerControlException('Unable to register CLI worker stop signals.');
            }
        }
        pcntl_async_signals(true);
    }

    /**
     * Reports whether SIGTERM or SIGINT requested shutdown.
     *
     * @return bool Whether shutdown was requested.
     */
    #[Override]
    public function stopRequested(): bool
    {
        return $this->stopped;
    }

    /**
     * Waits in short intervals so shutdown also handles a signal received immediately before waiting.
     *
     * @param positive-int $seconds The maximum idle wait in seconds.
     *
     * @return void
     */
    #[Override]
    public function wait(int $seconds): void
    {
        for ($second = 0 ; $second < $seconds && !$this->stopped ; ++$second) {
            for ($tick = 0 ; $tick < 10 && !$this->stopped ; ++$tick) {
                usleep(100_000);
            }
        }
    }

    /**
     * Restores prior signal handlers and asynchronous dispatch settings.
     *
     * @return void
     *
     * @throws WorkerControlException When a previous handler cannot be restored.
     */
    #[Override]
    public function finish(): void
    {
        if ($this->handlers === []) {
            return;
        }
        $restored = true;
        foreach ($this->handlers as $signal => $handler) {
            if (!pcntl_signal($signal, $handler)) {
                $restored = false;
            }
        }
        pcntl_async_signals($this->previousAsync);
        $this->handlers = [];
        if (!$restored) {
            throw new WorkerControlException('Unable to restore signal handlers after the CLI worker stopped.');
        }
    }

    /**
     * Requests cooperative shutdown without aborting the current processing attempt.
     *
     * @return void
     */
    private function requestStop(): void
    {
        $this->stopped = true;
    }
}
