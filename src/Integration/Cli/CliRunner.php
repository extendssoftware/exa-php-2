<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Cli\ExitCode;
use ExtendsSoftware\ExaPHP\Cli\Help\HelpRenderer;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\CliRunException;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliArgumentsException;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use Throwable;

use function array_is_list;
use function array_slice;
use function count;
use function is_string;
use function in_array;

/**
 * Runs one CLI command through an explicitly configured application and shuts it down.
 */
final readonly class CliRunner
{
    /**
     * Bootstraps the application, dispatches one command, returns its exit code, and shuts down.
     *
     * Modules must already be registered. Arguments include the script path and optional command name. Invalid process
     * arguments fail before bootstrap. After successful bootstrap, shutdown is attempted even after engine errors.
     * No command, --list, --help, and -h list commands. A command followed only by --help or -h displays its help.
     * Help bypasses input parsing and handler resolution. Bootstrap failure cleanup belongs to Application. Failures propagate without printing or exiting the process.
     *
     * @param Application $application The configured application, stopped after this run.
     * @param list<string> $argv Process arguments including script path and optional command name and tokens.
     *
     * @return int The handler's exit code, including application-specific codes.
     *
     * @throws InvalidCliArgumentsException When arguments are malformed or the script path is missing.
     * @throws InvalidCliConfigurationException When runner services have incompatible types.
     * @throws CliRunException When execution and shutdown both fail, preserving both failures.
     * @throws Throwable When bootstrap, execution, or shutdown alone fails, propagated unchanged.
     */
    public function run(Application $application, array $argv): int
    {
        if (!array_is_list($argv) || count($argv) < 1) {
            throw new InvalidCliArgumentsException('CLI arguments must include the script path.');
        }
        foreach ($argv as $argument) {
            if (!is_string($argument)) {
                throw new InvalidCliArgumentsException('CLI arguments must be a list of strings.');
            }
        }
        if ($argv[0] === '' || (isset($argv[1]) && $argv[1] === '')) {
            throw new InvalidCliArgumentsException('CLI script path and command name must not be empty.');
        }
        $services = $application->bootstrap();
        $failure = null;
        try {
            $output = $services->get(Output::class);
            if (!$output instanceof Output) {
                throw new InvalidCliConfigurationException('The CLI Output service must implement Output.');
            }
            $tokens = array_slice($argv, 1);
            $listing = $tokens === [] || (count($tokens) === 1 && in_array($tokens[0], ['--list', '--help', '-h'], true));
            $help = count($tokens) === 2 && in_array($tokens[1], ['--help', '-h'], true);
            if ($listing || $help) {
                $registry = $services->get(CommandRegistry::class);
                $renderer = $services->get(HelpRenderer::class);
                if (!$registry instanceof CommandRegistry || !$renderer instanceof HelpRenderer) {
                    throw new InvalidCliConfigurationException('CLI help services must implement their contracts.');
                }
                $text = $listing
                    ? $renderer->commandList($registry->all(), $argv[0])
                    : $renderer->commandHelp($registry->get($tokens[0]), $argv[0]);
                $output->write($text);
                $exitCode = ExitCode::Success->value;
            } else {
                $dispatcher = $services->get(CommandDispatcher::class);
                if (!$dispatcher instanceof CommandDispatcher) {
                    throw new InvalidCliConfigurationException('The CLI dispatcher service must be a CommandDispatcher.');
                }
                $exitCode = $dispatcher->dispatch($argv[1], array_slice($argv, 2), $output);
            }
        } catch (Throwable $exception) {
            // Engine errors also require application cleanup before propagation.
            $failure = $exception;
        }
        try {
            $application->shutdown();
        } catch (Throwable $exception) {
            if ($failure !== null) {
                throw new CliRunException($failure, $exception);
            }
            throw $exception;
        }
        if ($failure !== null) {
            throw $failure;
        }

        return $exitCode;
    }
}
