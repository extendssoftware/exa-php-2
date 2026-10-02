<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Logging\Factory;

use ExtendsSoftware\ExaPHP\Integration\Logging\Exception\InvalidLoggingConfigurationException;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\WriterLogger;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocatorException;

/**
 * Creates loggers using the configured writer service.
 */
final readonly class LoggerFactory
{
    /**
     * Resolves the writer and creates an independent logger without writing a record.
     *
     * @param ServiceLocator $serviceLocator The locator providing the LogWriter service.
     *
     * @return WriterLogger The logger using the resolved writer.
     *
     * @throws InvalidLoggingConfigurationException When the writer service does not implement LogWriter.
     * @throws ServiceLocatorException When the writer cannot be resolved, propagated unchanged.
     */
    public function create(ServiceLocator $serviceLocator): WriterLogger
    {
        $writer = $serviceLocator->get(LogWriter::class);
        if (!$writer instanceof LogWriter) {
            throw new InvalidLoggingConfigurationException('The writer service must implement LogWriter.');
        }

        return new WriterLogger($writer);
    }
}
