<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging;

use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use PHPUnit\Framework\TestCase;
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

    public function testPropagatesWriterFailureUnchanged(): void
    {
        $failure = new LogStreamWriteException('Failed');
        $writer = $this->createStub(LogWriter::class);
        $writer->method('write')->willThrowException($failure);
        $this->expectExceptionObject($failure);

        new WriterLogger($writer)->log(LogLevel::Error, 'Failed');
    }
}
