<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging\Formatter;

use DateTime;
use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogFormattingException;
use ExtendsSoftware\ExaPHP\Logging\Formatter\JsonLogFormatter;
use ExtendsSoftware\ExaPHP\Logging\LoggingException;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use TypeError;

use function fclose;
use function fopen;
use function ini_get;
use function ini_set;
use function json_decode;
use function str_repeat;
use function substr_count;

use const INF;
use const JSON_THROW_ON_ERROR;
use const NAN;

final class JsonLogFormatterTest extends TestCase
{
    public function testFormatsOneLineWithStructuredContextAndAnExplicitTimestamp(): void
    {
        $date = new DateTimeImmutable('2026-10-02T12:34:56.123456+02:00');
        $context = ['nested' => ['values' => [null, false, 0, 1.0]], 'date' => $date, 'level' => LogLevel::Info];
        $record = new LogRecord(LogLevel::Warning, "Hello\n世界\r\nhttps://example.test", $date, $context);
        $line = new JsonLogFormatter()->format($record);
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, substr_count($line, "\n"));
        self::assertStringEndsWith("\n", $line);
        self::assertStringNotContainsString("\r", $line);
        self::assertSame('2026-10-02T10:34:56.123456Z', $decoded['timestamp']);
        self::assertSame('2026-10-02T12:34:56.123456+02:00', $date->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('warning', $decoded['level']);
        self::assertSame($record->message, $decoded['message']);
        self::assertSame([null, false, 0, 1.0], $decoded['context']['nested']['values']);
        self::assertSame($decoded['timestamp'], $decoded['context']['date']);
        self::assertSame('info', $decoded['context']['level']);
        self::assertSame($context, $record->context);
    }

    public function testNormalizesMutableContextDatesWithoutChangingThem(): void
    {
        $date = new DateTime('2026-10-02T23:34:56.654321-04:00');
        $line = new JsonLogFormatter()->format($this->record(['nested' => ['date' => $date]]));
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('2026-10-03T03:34:56.654321Z', $decoded['context']['nested']['date']);
        self::assertSame('2026-10-02T23:34:56.654321-04:00', $date->format('Y-m-d\TH:i:s.uP'));
    }

    public function testEmptyContextIsAJsonObject(): void
    {
        $line = new JsonLogFormatter()->format($this->record());
        self::assertInstanceOf(stdClass::class, json_decode($line, flags: JSON_THROW_ON_ERROR)->context);
    }

    public function testObjectsDoNotExecuteSerializationCallbacksOrExposeProperties(): void
    {
        $value = new class implements JsonSerializable {
            public string $secret = 'do-not-log';

            public function jsonSerialize(): mixed
            {
                throw new RuntimeException('Must not be called');
            }
        };
        $line = new JsonLogFormatter()->format($this->record(['object' => $value]));
        $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['class' => $value::class], $decoded['context']['object']);
        self::assertStringNotContainsString('do-not-log', $line);
        self::assertSame('do-not-log', $value->secret);
    }

    public function testIncludesExceptionsAndPreviousErrorsWithoutTraceArguments(): void
    {
        $setting = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $factory = static fn(string $secret): RuntimeException =>
                new RuntimeException('Failure', 42, new TypeError('Cause'));
            $exception = $factory('sensitive-argument');
            self::assertArrayHasKey('args', $exception->getTrace()[0]);
            $line = new JsonLogFormatter()->format($this->record(['exception' => $exception]));
        } finally {
            ini_set('zend.exception_ignore_args', $setting);
        }
        $details = json_decode($line, true, flags: JSON_THROW_ON_ERROR)['context']['exception'];
        self::assertSame(RuntimeException::class, $details['class']);
        self::assertSame('Failure', $details['message']);
        self::assertSame(42, $details['code']);
        self::assertSame($exception->getFile(), $details['file']);
        self::assertSame($exception->getLine(), $details['line']);
        self::assertSame(TypeError::class, $details['previous']['class']);
        self::assertNull($details['previous']['previous']);
        self::assertNotEmpty($details['trace']);
        foreach ($details['trace'] as $frame) {
            self::assertArrayNotHasKey('args', $frame);
            self::assertArrayNotHasKey('object', $frame);
        }
        self::assertStringNotContainsString('sensitive-argument', $line);
    }

    public function testTruncatesLongTraces(): void
    {
        $exception = $this->deepException(60);
        $line = new JsonLogFormatter()->format($this->record(['exception' => $exception]));
        $details = json_decode($line, true, flags: JSON_THROW_ON_ERROR)['context']['exception'];
        self::assertCount(50, $details['trace']);
        self::assertTrue($details['traceTruncated']);
    }

    public function testRejectsRecursiveContext(): void
    {
        $context = [];
        $context['cycle'] = &$context;
        $this->expectException(LogFormattingException::class);
        new JsonLogFormatter()->format($this->record($context));
    }

    public function testRejectsExcessivePreviousExceptionChains(): void
    {
        $exception = null;
        for ($i = 0; $i < 35; ++$i) {
            $exception = new RuntimeException('Failure', 0, $exception);
        }
        $this->expectException(LogFormattingException::class);
        new JsonLogFormatter()->format($this->record(['exception' => $exception]));
    }

    #[DataProvider('unencodableValues')]
    public function testWrapsJsonEncodingFailures(mixed $value): void
    {
        try {
            new JsonLogFormatter()->format($this->record(['value' => $value]));
            self::fail('Expected a formatting failure.');
        } catch (LogFormattingException $exception) {
            self::assertInstanceOf(LoggingException::class, $exception);
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function unencodableValues(): iterable
    {
        yield ["\xB1\x31"];
        yield [INF];
        yield [NAN];
    }

    public function testRejectsResourcesWithoutClosingThem(): void
    {
        $stream = fopen('php://memory', 'w+');
        try {
            try {
                new JsonLogFormatter()->format($this->record(['stream' => $stream]));
                self::fail('Expected a resource formatting failure.');
            } catch (LogFormattingException $exception) {
                self::assertStringContainsString('unsupported', $exception->getMessage());
                self::assertIsResource($stream);
            }
        } finally {
            fclose($stream);
        }
        $this->expectException(LogFormattingException::class);
        new JsonLogFormatter()->format($this->record(['stream' => $stream]));
    }

    public function testRejectsOversizedRecords(): void
    {
        $this->expectException(LogFormattingException::class);
        new JsonLogFormatter()->format($this->record(['large' => str_repeat('x', 1_048_576)]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(array $context = []): LogRecord
    {
        return new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable('2026-10-02T00:00:00Z'), $context);
    }

    private function deepException(int $remaining): RuntimeException
    {
        return $remaining > 0 ? $this->deepException($remaining - 1) : new RuntimeException('Deep failure');
    }
}
