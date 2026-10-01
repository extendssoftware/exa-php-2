<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Event;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Event\EventDispatcher;
use ExtendsSoftware\ExaPHP\Event\SynchronousEventDispatcher;
use ExtendsSoftware\ExaPHP\Integration\Event\EventModule;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class EventModuleIntegrationTest extends TestCase
{
    public function testApplicationBootstrapsWithTheEventModule(): void
    {
        $directory = sys_get_temp_dir() . '/exa-event-module-' . bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $application = new Application($directory);
            $application->registerModule(EventModule::class);
            $serviceLocator = $application->bootstrap();

            self::assertInstanceOf(Configuration::class, $serviceLocator->get(Configuration::class));
            $dispatcher = $serviceLocator->get(EventDispatcher::class);
            self::assertInstanceOf(SynchronousEventDispatcher::class, $dispatcher);
            self::assertSame($dispatcher, $serviceLocator->get(EventDispatcher::class));
            self::assertSame($serviceLocator, $application->bootstrap());
            $application->shutdown();
        } finally {
            rmdir($directory);
        }
    }
}
