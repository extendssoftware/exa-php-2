<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Logging;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LogRecordTest extends TestCase
{
    public function testPreservesTheSuppliedRecordDataWithoutNormalizingIt(): void
    {
        $timestamp = new DateTimeImmutable('2026-10-02T12:34:56.123456+02:00');
        $details = new stdClass();
        $context = ['details' => $details, 'nullable' => null, 'nested' => ['id' => 42]];
        $record = new LogRecord(LogLevel::Warning, 'Article {id} unavailable.', $timestamp, $context);

        self::assertSame(LogLevel::Warning, $record->level);
        self::assertSame('Article {id} unavailable.', $record->message);
        self::assertSame($timestamp, $record->timestamp);
        self::assertSame($context, $record->context);

        $context['nested']['id'] = 43;
        self::assertSame(42, $record->context['nested']['id']);
        self::assertSame($details, $record->context['details']);
    }

    public function testContextDefaultsToAnEmptyArray(): void
    {
        $record = new LogRecord(LogLevel::Debug, '', new DateTimeImmutable('2026-10-02T00:00:00Z'));

        self::assertSame([], $record->context);
        self::assertSame('', $record->message);
    }
}
