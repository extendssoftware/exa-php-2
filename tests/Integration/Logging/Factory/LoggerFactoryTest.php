<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Logging\Factory;

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
    public function testCreatesIndependentLoggersUsingTheResolvedWriter(): void
    {
        $writer = $this->createMock(LogWriter::class);
        $writer->expects($this->once())->method('write')->with($this->callback(
            static fn(LogRecord $record): bool => $record->message === 'Message'
                && $record->level === LogLevel::Info && $record->context === ['id' => 1],
        ));
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects($this->exactly(2))->method('get')->with(LogWriter::class)->willReturn($writer);
        $factory = new LoggerFactory();
        $logger = $factory->create($locator);
        $this->assertNotSame($logger, $factory->create($locator));
        $logger->log(LogLevel::Info, 'Message', ['id' => 1]);
    }

    public function testRejectsIncorrectWriterService(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidLoggingConfigurationException::class);
        new LoggerFactory()->create($locator);
    }

    public function testPreservesResolutionFailure(): void
    {
        $failure = new ServiceNotFoundException('Missing writer');
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willThrowException($failure);
        try {
            new LoggerFactory()->create($locator);
            $this->fail('Expected resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            $this->assertSame($failure, $exception);
        }
    }
}
