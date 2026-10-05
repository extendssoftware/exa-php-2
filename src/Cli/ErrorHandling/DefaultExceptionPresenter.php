<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\ErrorHandling;

use ExtendsSoftware\ExaPHP\Cli\ExitCode;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use Override;
use Throwable;

use function str_replace;

/**
 * Presents usage messages and hides all other exception details behind a generic failure message.
 */
final readonly class DefaultExceptionPresenter implements ExceptionPresenter
{
    /**
     * Writes one error line and returns the corresponding conventional exit code.
     *
     * Usage messages have carriage returns, newlines, and escape bytes replaced with spaces. Other exception
     * messages, types, codes, previous exceptions, and traces are not exposed. No logging is performed.
     *
     * @param Throwable $exception The failure to classify and present.
     * @param Output $output The error output destination.
     *
     * @return int InvalidUsage for UsageException; Failure for every other failure.
     *
     * @throws Throwable When output fails, propagated unchanged.
     */
    #[Override]
    public function present(Throwable $exception, Output $output): int
    {
        if ($exception instanceof UsageException) {
            $message = str_replace(["\r", "\n", "\e"], ' ', $exception->getMessage());
            $output->writeError('Error: ' . $message . "\n");

            return ExitCode::InvalidUsage->value;
        }
        $output->writeError("Error: Command execution failed.\n");

        return ExitCode::Failure->value;
    }
}
