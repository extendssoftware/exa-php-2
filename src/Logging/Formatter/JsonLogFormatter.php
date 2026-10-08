<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Logging\Formatter;

use Override;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use ExtendsSoftware\ExaPHP\Logging\Formatter\Exception\LogFormattingException;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use JsonException;
use Throwable;
use UnitEnum;

use function array_intersect_key;
use function array_slice;
use function count;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_resource;
use function json_encode;
use function sprintf;
use function strlen;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Formats records as newline-delimited JSON with inline exception details.
 *
 * Context nesting is limited to 32 levels, exception traces to 50 frames, and output to 1 MiB including its newline.
 * Trace arguments and objects are excluded. Arbitrary objects are represented by class name without invoking callbacks.
 */
final readonly class JsonLogFormatter implements LogFormatter
{
    /**
     * Maximum normalization nesting depth, including previous exceptions.
     */
    private const int MAX_DEPTH = 32;

    /**
     * Maximum number of frames included for each exception.
     */
    private const int MAX_TRACE_FRAMES = 50;

    /**
     * Maximum encoded record size in bytes, including the trailing newline.
     */
    private const int MAX_RECORD_BYTES = 1_048_576;

    /**
     * {@inheritDoc}
     *
     * Produces compact JSON followed by exactly one LF. Timestamps use UTC with microseconds. Empty context becomes
     * an object. Arrays retain keys; dates and enums become strings or backed scalar values. Resources are rejected.
     *
     * @throws LogFormattingException When normalization, JSON encoding, or the output size limit prevents formatting.
     */
    #[Override]
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $this->formatTimestamp($record->timestamp),
            'level' => $record->level->value,
            'message' => $record->message,
            'context' => (object)$this->normalize($record->context, 0),
        ];

        try {
            $json = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new LogFormattingException('Could not encode log record as JSON.', 0, $exception);
        }

        if (strlen($json) + 1 > self::MAX_RECORD_BYTES) {
            throw new LogFormattingException('Formatted log record exceeds the 1 MiB size limit.');
        }

        return $json . "\n";
    }

    /**
     * Formats a date in UTC without modifying the supplied object.
     *
     * @param DateTimeInterface $timestamp The date to format.
     *
     * @return string The UTC timestamp with microseconds and a Z suffix.
     */
    private function formatTimestamp(DateTimeInterface $timestamp): string
    {
        return DateTimeImmutable::createFromInterface($timestamp)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Normalizes context values without invoking application serialization methods.
     *
     * @param mixed $value The value to normalize.
     * @param int $depth The current nesting depth.
     *
     * @return mixed A JSON-compatible value.
     *
     * @throws LogFormattingException When nesting is excessive or a resource is encountered.
     */
    private function normalize(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new LogFormattingException('Log context exceeds the maximum nesting depth of 32.');
        }

        if ($value instanceof Throwable) {
            return $this->normalizeException($value, $depth);
        }
        if ($value instanceof DateTimeInterface) {
            return $this->formatTimestamp($value);
        }
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if (is_array($value)) {
            return array_map(function ($entry) use ($depth) {
                return $this->normalize($entry, $depth + 1);
            }, $value);
        }
        if (is_object($value)) {
            return ['class' => $value::class];
        }
        if (is_resource($value) || get_debug_type($value) === 'resource (closed)') {
            throw new LogFormattingException(
                sprintf('Log context contains an unsupported %s.', get_debug_type($value)),
            );
        }

        return $value;
    }

    /**
     * Extracts bounded exception details without trace arguments or custom properties.
     *
     * @param Throwable $exception The exception or engine error.
     * @param int $depth The current nesting depth.
     *
     * @return array<string, mixed> The structured exception details.
     *
     * @throws LogFormattingException When the previous-exception chain exceeds the nesting limit.
     */
    private function normalizeException(Throwable $exception, int $depth): array
    {
        $trace = $exception->getTrace();
        $frames = [];
        foreach (array_slice($trace, 0, self::MAX_TRACE_FRAMES) as $frame) {
            $frames[] = array_intersect_key($frame, [
                'file' => true,
                'line' => true,
                'class' => true,
                'type' => true,
                'function' => true,
            ]);
        }

        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $frames,
            'traceTruncated' => count($trace) > self::MAX_TRACE_FRAMES,
            'previous' => $this->normalize($exception->getPrevious(), $depth + 1),
        ];
    }
}
