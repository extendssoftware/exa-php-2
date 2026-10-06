<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Logging\Factory;

use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Integration\Logging\Exception\InvalidLoggingConfigurationException;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates loggers using the configured writer and clock services.
 */
final readonly class LoggerFactory
{
    /**
     * Resolves the writer and clock and creates an independent logger without writing a record.
     *
     * @param ServiceLocator $serviceLocator The locator providing the LogWriter and Clock services.
     *
     * @return WriterLogger The logger using the resolved writer and clock.
     *
     * @throws InvalidLoggingConfigurationException When a writer or clock service has an invalid type.
     * @throws ServiceLocatorException When the writer or clock cannot be resolved, propagated unchanged.
     */
    public function create(ServiceLocator $serviceLocator): WriterLogger
    {
        $writer = $serviceLocator->get(LogWriter::class);
        if (!$writer instanceof LogWriter) {
            throw new InvalidLoggingConfigurationException('The writer service must implement LogWriter.');
        }

        $clock = $serviceLocator->get(Clock::class);
        if (!$clock instanceof Clock) {
            throw new InvalidLoggingConfigurationException('The clock service must implement Clock.');
        }

        return new WriterLogger($writer, $clock);
    }
}
