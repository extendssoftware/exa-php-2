<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging;

/**
 * Accepts diagnostic messages with severity and contextual data.
 */
interface Logger
{
    /**
     * Submits a message at the specified severity level.
     *
     * Context supplies additional information about the message without changing its severity.
     *
     * @param LogLevel $level The message severity.
     * @param string $message The diagnostic message.
     * @param array<string, mixed> $context Additional contextual data indexed by descriptive keys.
     *
     * @return void
     *
     * @throws LoggingException When logging fails.
     */
    public function log(LogLevel $level, string $message, array $context = []): void;
}
