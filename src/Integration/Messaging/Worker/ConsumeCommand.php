<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Worker;

use ExtendsSoftware\ExaPHP\Cli\ExitCode;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Worker\WorkerControl;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\WorkerRunException;
use ExtendsSoftware\ExaPHP\Messaging\Consumption\MessageProcessor;
use Override;
use Throwable;

/**
 * Runs messaging processing until shutdown is requested or a single poll completes.
 */
final readonly class ConsumeCommand implements CommandHandler
{
    /**
     * Creates a worker using an existing processor and lifecycle collaborators.
     *
     * @param MessageProcessor $processor The processor invoked once per poll.
     * @param WorkerSettings $settings The idle wait duration.
     * @param WorkerControl $control The stop and idle waiting mechanism for persistent execution.
     */
    public function __construct(
        private MessageProcessor $processor,
        private WorkerSettings $settings,
        private WorkerControl $control,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * `--once` performs at most one attempt without signal control or waiting. Persistent execution waits only after
     * empty polls. Stop requests let the current attempt finish. Success returns the success exit code.
     * Failures propagate after signal cleanup without retrying the worker operation.
     */
    #[Override]
    public function handle(Input $input, Output $output): int
    {
        if (($input->options['once'] ?? false) === true) {
            $this->processor->process();

            return ExitCode::Success->value;
        }

        $this->control->start();
        $failure = null;
        try {
            while (!$this->control->stopRequested()) {
                $processed = $this->processor->process();
                if (!$processed && !$this->control->stopRequested()) {
                    $this->control->wait($this->settings->idleDelaySeconds);
                }
            }
        } catch (Throwable $exception) {
            // Engine errors also require restoration of process signal state.
            $failure = $exception;
        }
        try {
            $this->control->finish();
        } catch (Throwable $exception) {
            if ($failure !== null) {
                throw new WorkerRunException($failure, $exception);
            }
            throw $exception;
        }
        if ($failure !== null) {
            throw $failure;
        }

        return ExitCode::Success->value;
    }
}
