<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\ClockException;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Logging\Exception\LogTimestampException;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class WriterLoggerTest extends TestCase
{
    public function testCreatesUtcRecordWithOriginalContext(): void
    {
        $context = ['object' => new stdClass()];
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->once())->method('write')->with($this->callback(
            static fn(LogRecord $record): bool => $record->level === LogLevel::Info
                && $record->message === 'Published'
                && $record->context === $context
                && $record->timestamp->getTimezone()->getName() === 'UTC',
        ));

        new WriterLogger($writer)->log(LogLevel::Info, 'Published', $context);
    }

    public function testPreservesTheInjectedClockTimestamp(): void
    {
        $time = new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00');
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->once())->method('write')->with($this->callback(
            static fn(LogRecord $record): bool => $record->timestamp === $time,
        ));

        new WriterLogger($writer, new FrozenClock($time))->log(LogLevel::Info, 'Message');
    }

    public function testReadsTheClockForEachRecord(): void
    {
        $first = new DateTimeImmutable('2026-10-06T12:00:00Z');
        $second = $first->modify('+1 second');
        $clock = $this->createMock(Clock::class);
        $clock->expects($this->exactly(2))->method('now')->willReturn($first, $second);
        $records = [];
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->exactly(2))->method('write')->willReturnCallback(
            static function (LogRecord $record) use (&$records): void {
                $records[] = $record->timestamp;
            },
        );
        $logger = new WriterLogger($writer, $clock);

        $logger->log(LogLevel::Info, 'First');
        $logger->log(LogLevel::Info, 'Second');

        self::assertSame([$first, $second], $records);
    }

    public function testTranslatesClockFailuresWithoutWritingARecord(): void
    {
        $failure = new class ('Clock unavailable') extends RuntimeException implements ClockException {
        };
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willThrowException($failure);
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->never())->method('write');

        try {
            new WriterLogger($writer, $clock)->log(LogLevel::Error, 'Message');
            self::fail('Expected timestamp failure.');
        } catch (LogTimestampException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testPropagatesWriterFailureUnchanged(): void
    {
        $failure = new LogStreamWriteException('Failed');
        $writer = $this->createStub(LogWriter::class);
        $writer->method('write')->willThrowException($failure);
        $this->expectExceptionObject($failure);

        new WriterLogger($writer)->log(LogLevel::Error, 'Failed');
    }
}
