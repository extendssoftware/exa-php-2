<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\DefaultExceptionPresenter;
use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\ExceptionPresenter;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use Throwable;

/**
 * Presents CLI lifecycle failures after the runner has completed its cleanup attempts.
 */
final readonly class ExceptionHandlingCliRunner
{
    /**
     * Creates an error boundary with explicit execution and presentation collaborators.
     *
     * @param CliRunner $runner The runner managing application execution and shutdown.
     * @param ExceptionPresenter $presenter The failure presenter, replaceable for application logging or formatting.
     */
    public function __construct(
        private CliRunner $runner = new CliRunner(),
        private ExceptionPresenter $presenter = new DefaultExceptionPresenter(),
    ) {
    }

    /**
     * Runs the application and converts lifecycle failures into error output and exit codes.
     *
     * Successful return codes remain unchanged. The explicit output also handles failures before services exist.
     * Presentation is attempted once, after the underlying runner's cleanup. Presentation failures propagate unchanged
     * rather than being presented recursively. Neither this boundary nor its default presenter terminates the process.
     *
     * @param Application $application The application with its modules already registered.
     * @param list<string> $argv The complete process arguments, including the script path.
     * @param Output $errorOutput The output available independently of application bootstrap.
     *
     * @return int The command's exit code or the presenter's failure exit code.
     *
     * @throws Throwable When error presentation fails, propagated unchanged.
     */
    public function run(Application $application, array $argv, Output $errorOutput): int
    {
        try {
            return $this->runner->run($application, $argv);
        } catch (Throwable $exception) {
            // CLI presentation deliberately covers engine errors as well as exceptions.
            return $this->presenter->present($exception, $errorOutput);
        }
    }
}
