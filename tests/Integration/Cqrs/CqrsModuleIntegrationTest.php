<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cqrs;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryBus;
use ExtendsSoftware\ExaPHP\Cqrs\Query\SynchronousQueryBus;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\CqrsModule;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class CqrsModuleIntegrationTest extends TestCase
{
    public function testApplicationBootstrapsWithTheCqrsModule(): void
    {
        $directory = sys_get_temp_dir() . '/exa-cqrs-module-' . bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $application = new Application($directory);
            $application->registerModule(CqrsModule::class);
            $serviceLocator = $application->bootstrap();

            self::assertInstanceOf(Configuration::class, $serviceLocator->get(Configuration::class));
            $commandBus = $serviceLocator->get(CommandBus::class);
            $queryBus = $serviceLocator->get(QueryBus::class);
            self::assertInstanceOf(SynchronousCommandBus::class, $commandBus);
            self::assertInstanceOf(SynchronousQueryBus::class, $queryBus);
            self::assertSame($commandBus, $serviceLocator->get(CommandBus::class));
            self::assertSame($queryBus, $serviceLocator->get(QueryBus::class));
            self::assertSame($serviceLocator, $application->bootstrap());
            $application->shutdown();
        } finally {
            rmdir($directory);
        }
    }
}
