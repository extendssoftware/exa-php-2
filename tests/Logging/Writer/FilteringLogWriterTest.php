<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging\Writer;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\FilteringLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FilteringLogWriterTest extends TestCase
{
    public function testEvaluatesEachRecordOnceAndForwardsOnlyAcceptedOriginalRecord(): void
    {
        $rejected = new LogRecord(LogLevel::Debug, 'Debug', new DateTimeImmutable('2026-10-02T12:00:00Z'));
        $accepted = new LogRecord(LogLevel::Critical, 'Critical', new DateTimeImmutable('2026-10-02T12:00:01Z'));
        $evaluated = [];
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->once())->method('write')->with($this->identicalTo($accepted));
        $filter = new FilteringLogWriter($writer, static function (LogRecord $record) use (&$evaluated): bool {
            $evaluated[] = $record;

            return $record->level === LogLevel::Critical;
        });

        $filter->write($rejected);
        $filter->write($accepted);

        $this->assertSame([$rejected, $accepted], $evaluated);
    }

    public function testRejectedRecordDoesNotCallWriter(): void
    {
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->never())->method('write');

        new FilteringLogWriter($writer, static fn(LogRecord $record): bool => false)->write(
            new LogRecord(LogLevel::Info, 'Ignored', new DateTimeImmutable('2026-10-02T12:00:00Z')),
        );
    }

    public function testPropagatesPredicateFailureWithoutCallingWriter(): void
    {
        $failure = new LogicException('Predicate failed');
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->never())->method('write');
        $filter = new FilteringLogWriter($writer, static fn(LogRecord $record): bool => throw $failure);

        try {
            $filter->write(new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable('2026-10-02T12:00:00Z')));
            $this->fail('Expected predicate failure.');
        } catch (LogicException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testPropagatesWriterFailureUnchanged(): void
    {
        $failure = new LogStreamWriteException('Writer failed');
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->once())->method('write')->willThrowException($failure);
        $filter = new FilteringLogWriter($writer, static fn(LogRecord $record): bool => true);

        try {
            $filter->write(new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable('2026-10-02T12:00:00Z')));
            $this->fail('Expected writer failure.');
        } catch (LogStreamWriteException $exception) {
            $this->assertSame($failure, $exception);
        }
    }
}
