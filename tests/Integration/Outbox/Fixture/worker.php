<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\ExceptionHandlingCliRunner;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;
use ExtendsSoftware\ExaPHP\Integration\Outbox\OutboxModule;

require __DIR__ . '/../../../../vendor/autoload.php';

$application = new Application(__DIR__ . '/config');
$application->registerModule(CliModule::class);
$application->registerModule(ClockModule::class);
$application->registerModule(OutboxModule::class);
exit(new ExceptionHandlingCliRunner()->run($application, $argv, new StreamOutput(STDOUT, STDERR)));
