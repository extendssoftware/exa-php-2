<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli\Fixture;

use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use TypeError;

/**
 * Records command execution and shutdown for runner integration tests.
 */
final class RunnerHandler implements CommandHandler
{
    /**
     * Recorded lifecycle calls.
     *
     * @var list<string>
     */
    public array $calls = [];

    /**
     * Creates a configurable failure probe.
     *
     * @param bool $fail Whether command execution fails.
     * @param bool $shutdownFails Whether shutdown fails.
     */
    public function __construct(private readonly bool $fail, private readonly bool $shutdownFails)
    {
    }

    /**
     * Writes parsed input and returns an application-specific exit code.
     *
     * @param Input $input The parsed command.
     * @param Output $output The output channels.
     *
     * @return int The test exit code.
     *
     * @throws TypeError When execution failure is requested.
     */
    public function handle(Input $input, Output $output): int
    {
        $this->calls[] = 'handle';
        if ($this->fail) {
            throw new TypeError('command failed');
        }
        $output->write($input->arguments['name']);

        return 17;
    }

    /**
     * Records shutdown and optionally fails.
     *
     * @return void
     *
     * @throws TypeError When shutdown failure is requested.
     */
    public function shutdown(): void
    {
        $this->calls[] = 'shutdown';
        if ($this->shutdownFails) {
            throw new TypeError('shutdown failed');
        }
    }
}
