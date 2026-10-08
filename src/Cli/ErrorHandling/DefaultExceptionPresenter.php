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
     * {@inheritDoc}
     *
     * Replaces carriage returns, newlines, and escape bytes in usage messages with spaces.
     * Returns `ExitCode::InvalidUsage` for `UsageException` and `ExitCode::Failure` for other failures.
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
