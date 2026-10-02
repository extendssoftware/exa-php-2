<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging;

use DateTimeImmutable;

/**
 * Carries a diagnostic message, severity, timestamp, and context.
 *
 * Record properties are immutable. Objects within context retain their identity and mutability.
 */
final readonly class LogRecord
{
    /**
     * Creates a record with an explicit timestamp.
     *
     * @param LogLevel $level The message severity.
     * @param string $message The diagnostic message.
     * @param DateTimeImmutable $timestamp The time associated with the message, preserving its timezone.
     * @param array<string, mixed> $context Additional contextual data indexed by descriptive keys.
     */
    public function __construct(
        public LogLevel $level,
        public string $message,
        public DateTimeImmutable $timestamp,
        public array $context = [],
    ) {
    }
}
