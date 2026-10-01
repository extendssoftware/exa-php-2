<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Exception\ModuleInstantiationException;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Fiber;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function bin2hex;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

final class ApplicationIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/exa-application-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/config');
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->directory);
    }

    public function testBootstrapsModulesInOrderAndExposesFinalConfigurationToServices(): void
    {
        $first = $this->module('First');
        $second = $this->module('Second');
        $this->write('First/config/defaults.php', "return ['module' => 'first', 'value' => 'module'];");
        $this->write('Second/config/defaults.php', "return ['module' => 'second'];");
        $this->write('config/test.global.php', "return ['value' => 'global'];");
        $this->write('config/test.local.php', <<<'PHP'
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

return [
    'value' => 'local',
    'services' => [
        'result' => new FactoryDefinition(
            static fn(ServiceLocator $services): object =>
                (object) ['value' => $services->get(Configuration::class)->get('value')],
        ),
    ],
];
PHP);
        $application = new Application($this->directory . '/config');
        $application->registerModule($first);
        $application->registerModule($second);
        self::assertFalse(file_exists($this->directory . '/constructed'));

        $locator = $application->bootstrap();

        self::assertSame('FirstSecond', file_get_contents($this->directory . '/constructed'));
        self::assertSame('second', $application->config()->get('module'));
        self::assertSame('local', $locator->get('result')->value);
        self::assertSame($application->config(), $locator->get(Configuration::class));

        $this->write('config/test.local.php', "throw new RuntimeException('Must not reload');");
        self::assertSame($locator, $application->bootstrap());
        self::assertSame('FirstSecond', file_get_contents($this->directory . '/constructed'));
    }

    public function testSupportsBootstrapWithoutModulesAndRejectsLaterRegistration(): void
    {
        $application = new Application($this->directory . '/config');
        $locator = $application->bootstrap();
        self::assertSame($application->config(), $locator->get(Configuration::class));
        $module = new class implements Module {};
        $this->expectException(ApplicationStateException::class);

        $application->registerModule($module::class);
    }

    public function testFailedBootstrapExposesNoPartialConfigurationAndCannotBeRetried(): void
    {
        $this->write('config/test.global.php', "return ['services' => ['invalid' => false]];");
        $application = new Application($this->directory . '/config');
        try {
            $application->bootstrap();
            self::fail('Expected invalid service configuration.');
        } catch (InvalidServiceDefinitionException $exception) {
            self::assertStringContainsString('invalid', $exception->getMessage());
        }

        foreach (['config', 'bootstrap'] as $method) {
            try {
                $application->$method();
                self::fail('Expected an invalid application state.');
            } catch (ApplicationStateException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $this->expectException(ApplicationStateException::class);
        $application->registerModule($this->module('Late'));
    }

    public function testModuleConstructionFailurePreservesItsCauseAndStopsLaterModules(): void
    {
        $application = new Application($this->directory . '/config');
        $broken = $this->module('Broken', "throw new RuntimeException('Constructor failed');");
        $application->registerModule($broken);
        $application->registerModule($this->module('Later'));

        try {
            $application->bootstrap();
            self::fail('Expected module construction to fail.');
        } catch (ModuleInstantiationException $exception) {
            self::assertStringContainsString($broken, $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertSame('Constructor failed', $exception->getPrevious()->getMessage());
        }

        self::assertSame('Broken', file_get_contents($this->directory . '/constructed'));
    }

    public function testRejectsOperationsDuringBootstrapAndCompletesTheOriginalAttempt(): void
    {
        $application = new Application($this->directory . '/config');
        $module = $this->module('Suspended', 'Fiber::suspend();');
        $application->registerModule($module);
        $fiber = new Fiber($application->bootstrap(...));
        $fiber->start();

        foreach (['bootstrap', 'config', 'registerModule'] as $method) {
            try {
                if ($method === 'registerModule') {
                    $application->registerModule($module);
                } else {
                    $application->$method();
                }
                self::fail('Expected bootstrap to reject the operation while in progress.');
            } catch (ApplicationStateException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $fiber->resume();
        self::assertSame($fiber->getReturn(), $application->bootstrap());
    }

    private function write(string $path, string $body): void
    {
        file_put_contents($this->directory . '/' . $path, "<?php\n\ndeclare(strict_types=1);\n\n" . $body);
    }

    /** @return class-string<Module> */
    private function module(string $name, string $constructor = ''): string
    {
        mkdir($this->directory . '/' . $name);
        mkdir($this->directory . '/' . $name . '/config');
        $class = 'ApplicationTestModule' . bin2hex(random_bytes(8));
        $label = var_export($name, true);
        $log = var_export($this->directory . '/constructed', true);
        $this->write($name . '/Module.php', <<<PHP
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;

/** Provides configuration for an application integration test. */
final readonly class $class implements Module, ConfigurableModule
{
    /** Records module construction. */
    public function __construct()
    {
        file_put_contents($log, $label, FILE_APPEND);
        $constructor
    }

    /**
     * Returns the module configuration directory.
     *
     * @return string The configuration directory.
     */
    public function configDirectory(): string
    {
        return __DIR__ . '/config';
    }
}
PHP);
        require $this->directory . '/' . $name . '/Module.php';

        return $class;
    }
}
