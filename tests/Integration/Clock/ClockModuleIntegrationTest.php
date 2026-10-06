<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Clock;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\Integration\Clock\ClockModule;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class ClockModuleIntegrationTest extends TestCase
{
    public function testRegistersASharedSystemClockIndependentlyOfOtherModules(): void
    {
        $directory = sys_get_temp_dir() . '/exa-clock-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $application = new Application($directory);
            $application->registerModule(ClockModule::class);
            $services = $application->bootstrap();
            $clock = $services->get(Clock::class);

            self::assertInstanceOf(SystemClock::class, $clock);
            self::assertSame($clock, $services->get(Clock::class));
            $application->shutdown();
        } finally {
            rmdir($directory);
        }
    }

    public function testApplicationConfigurationOverridesTheDefaultClock(): void
    {
        $directory = sys_get_temp_dir() . '/exa-clock-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/clock.global.php', <<<'CONFIG'
<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\FrozenClock;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return ['services' => [
    Clock::class => new FactoryDefinition(
        static fn(): Clock => new FrozenClock(new DateTimeImmutable('2026-10-06T12:34:56.123456+02:00')),
    ),
]];
CONFIG);
        try {
            $application = new Application($directory);
            $application->registerModule(ClockModule::class);
            $services = $application->bootstrap();
            $clock = $services->get(Clock::class);

            self::assertInstanceOf(FrozenClock::class, $clock);
            self::assertSame('2026-10-06T12:34:56.123456+02:00', $clock->now()->format('Y-m-d\TH:i:s.uP'));
            self::assertSame($clock, $services->get(Clock::class));
            $application->shutdown();
        } finally {
            unlink($directory . '/clock.global.php');
            rmdir($directory);
        }
    }
}
