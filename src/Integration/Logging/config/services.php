<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Integration\Logging\Factory\LoggerFactory;
use ExtendsSoftware\ExaPHP\Logging\Logger;
use ExtendsSoftware\ExaPHP\Logging\Writer\LogWriter;
use ExtendsSoftware\ExaPHP\Logging\Writer\StreamLogWriter;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        Logger::class => new FactoryDefinition(new LoggerFactory()->create(...)),
        LogWriter::class => new FactoryDefinition(static fn(): LogWriter => new StreamLogWriter('php://stderr')),
    ],
];
