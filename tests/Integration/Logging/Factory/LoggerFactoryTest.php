<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Logging\Factory;

use DateTimeImmutable;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Integration\Logging\Exception\InvalidLoggingConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Logging\Factory\LoggerFactory;
use ExtendsSoftware\ExaPHP\Logging\LogLevel;
use ExtendsSoftware\ExaPHP\Logging\LogRecord;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LoggerFactoryTest extends TestCase
{
    public function testCreatesIndependentLoggersUsingTheResolvedWriterAndClock(): void
    {
        $time = new DateTimeImmutable('2026-10-06T12:00:00Z');
        $clock = new FrozenClock($time);
        $writer = $this->createMock(LogWriter::class);
        $writer->expects(self::once())->method('write')->with(self::callback(
            static fn(LogRecord $record): bool => $record->message === 'Message'
                && $record->level === LogLevel::Info && $record->context === ['id' => 1]
                && $record->timestamp === $time,
        ));
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::exactly(4))->method('get')->willReturnMap([
            [LogWriter::class, $writer],
            [Clock::class, $clock],
        ]);
        $factory = new LoggerFactory();
        $logger = $factory->create($locator);
        self::assertNotSame($logger, $factory->create($locator));
        $logger->log(LogLevel::Info, 'Message', ['id' => 1]);
    }

    public function testRejectsIncorrectWriterService(): void
    {
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidLoggingConfigurationException::class);
        new LoggerFactory()->create($locator);
    }

    public function testRejectsIncorrectClockService(): void
    {
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willReturnMap([
            [LogWriter::class, self::createStub(LogWriter::class)],
            [Clock::class, new stdClass()],
        ]);
        $this->expectException(InvalidLoggingConfigurationException::class);
        new LoggerFactory()->create($locator);
    }

    public function testPreservesResolutionFailure(): void
    {
        $failure = new ServiceNotFoundException('Missing writer');
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willThrowException($failure);
        try {
            new LoggerFactory()->create($locator);
            self::fail('Expected resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
