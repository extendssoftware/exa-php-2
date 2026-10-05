<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Output;

use ExtendsSoftware\ExaPHP\Cli\CliException;

/**
 * Writes CLI output to the standard output and standard error channels.
 */
interface Output
{
    /**
     * Writes the supplied bytes to standard output without adding a newline.
     *
     * @param string $message The bytes to write, including any desired line endings.
     *
     * @return void
     *
     * @throws CliException When writing fails; some bytes may already have been written.
     */
    public function write(string $message): void;

    /**
     * Writes the supplied bytes to standard error without adding a newline.
     *
     * @param string $message The bytes to write, including any desired line endings.
     *
     * @return void
     *
     * @throws CliException When writing fails; some bytes may already have been written.
     */
    public function writeError(string $message): void;
}
