<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging\Writer;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\CompositeLogWriteException;
use ExtendsSoftware\ExaPHP\Logging\Writer\Exception\LogStreamWriteException;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\CompositeLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\FilteringLogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use PHPUnit\Framework\TestCase;
use TypeError;

final class CompositeLogWriterTest extends TestCase
{
    public function testDeliversOriginalRecordInOrderIncludingRepeatedWriters(): void
    {
        $record = $this->record();
        $calls = [];
        $first = $this->createMock(LogWriter::class);
        $first->expects($this->exactly(2))->method('write')->with($this->identicalTo($record))
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'first';
            });
        $second = $this->createMock(LogWriter::class);
        $second->expects($this->once())->method('write')->with($this->identicalTo($record))
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'second';
            });

        new CompositeLogWriter($first, $second, $first)->write($record);

        $this->assertSame(['first', 'second', 'first'], $calls);
    }

    public function testEmptyCompositeDiscardsRecord(): void
    {
        new CompositeLogWriter()->write($this->record());
        $this->expectNotToPerformAssertions();
    }

    public function testCollectsFailuresAndAttemptsRemainingWriters(): void
    {
        $firstFailure = new LogStreamWriteException('First failed');
        $lastFailure = new LogStreamWriteException('Last failed');
        $first = $this->createMock(LogWriter::class);
        $first->expects($this->once())->method('write')->willThrowException($firstFailure);
        $middle = $this->createMock(LogWriter::class);
        $middle->expects($this->once())->method('write');
        $last = $this->createMock(LogWriter::class);
        $last->expects($this->once())->method('write')->willThrowException($lastFailure);

        try {
            new CompositeLogWriter($first, $middle, $last)->write($this->record());
            $this->fail('Expected aggregate failure.');
        } catch (CompositeLogWriteException $exception) {
            $this->assertSame([0 => $firstFailure, 2 => $lastFailure], $exception->failures);
            $this->assertSame($firstFailure, $exception->getPrevious());
            $this->assertSame('Log delivery failed for 2 writer(s).', $exception->getMessage());
        }
    }

    public function testSingleFailureIsAggregatedAndDoesNotCarryIntoNextWrite(): void
    {
        $failure = new LogStreamWriteException('Failed once');
        $calls = 0;
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->exactly(2))->method('write')->willReturnCallback(
            static function () use (&$calls, $failure): void {
                if (++$calls === 1) {
                    throw $failure;
                }
            },
        );
        $composite = new CompositeLogWriter($writer);
        try {
            $composite->write($this->record());
            $this->fail('Expected aggregate failure.');
        } catch (CompositeLogWriteException $exception) {
            $this->assertSame([$failure], $exception->failures);
        }
        $composite->write($this->record());
    }

    public function testUnexpectedErrorStopsDeliveryAndPropagatesUnchanged(): void
    {
        $failure = new TypeError('Programming error');
        $first = $this->createMock(LogWriter::class);
        $first->expects($this->once())->method('write')->willThrowException($failure);
        $second = $this->createMock(LogWriter::class);
        $second->expects($this->never())->method('write');

        try {
            new CompositeLogWriter($first, $second)->write($this->record());
            $this->fail('Expected original error.');
        } catch (TypeError $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testDestinationsCanHaveIndependentFilters(): void
    {
        $record = $this->record();
        $accepted = $this->createMock(LogWriter::class);
        $accepted->expects($this->once())->method('write')->with($this->identicalTo($record));
        $rejected = $this->createMock(LogWriter::class);
        $rejected->expects($this->never())->method('write');

        new CompositeLogWriter(
            new FilteringLogWriter($accepted, static fn(LogRecord $record): bool => $record->level === LogLevel::Info),
            new FilteringLogWriter($rejected, static fn(LogRecord $record): bool => $record->level === LogLevel::Debug),
        )->write($record);
    }

    private function record(): LogRecord
    {
        return new LogRecord(LogLevel::Info, 'Message', new DateTimeImmutable('2026-10-02T12:00:00Z'));
    }
}
