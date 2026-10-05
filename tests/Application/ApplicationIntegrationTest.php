<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleBootstrapException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleInstantiationException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleShutdownException;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use Fiber;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use TypeError;

use function array_keys;
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
        $application->shutdown();
        $application->shutdown();
        $module = new class implements Module {};
        $this->expectException(ApplicationStateException::class);

        $application->registerModule($module::class);
    }

    public function testFailedBootstrapExposesNoPartialConfigurationAndCannotBeRetried(): void
    {
        $this->write('config/test.global.php', "return ['services' => ['invalid' => false]];");
        $application = new Application($this->directory . '/config');
        $application->registerModule($this->module('NotStarted', shutdown: $this->event('must-not-stop')));
        try {
            $application->bootstrap();
            self::fail('Expected invalid service configuration.');
        } catch (InvalidServiceDefinitionException $exception) {
            self::assertStringContainsString('invalid', $exception->getMessage());
        }

        $application->shutdown();
        self::assertFalse(file_exists($this->directory . '/events'));

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

        foreach (['bootstrap', 'config', 'registerModule', 'shutdown'] as $method) {
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

    public function testHooksUseTheSameModulesAndLocatorInForwardAndReverseOrder(): void
    {
        $first = $this->module(
            'First',
            bootstrap: '$this->hookServices = $services; ' . $this->event('start-first'),
            shutdown: <<<'PHP'
if ($this->hookServices !== $services) {
    throw new RuntimeException('Module or locator changed.');
}
PHP . $this->event('stop-first'),
        );
        $second = $this->module(
            'Second',
            bootstrap: $this->event('start-second'),
            shutdown: $this->event('stop-second'),
        );
        $shutdownOnly = $this->module('ShutdownOnly', shutdown: $this->event('stop-only'));
        $application = new Application($this->directory . '/config');
        foreach ([$first, $this->module('Plain'), $second, $shutdownOnly] as $module) {
            $application->registerModule($module);
        }

        $services = $application->bootstrap();
        self::assertSame('start-first;start-second;', file_get_contents($this->directory . '/events'));
        self::assertSame($services, $application->bootstrap());
        $application->shutdown();
        $application->shutdown();

        self::assertSame(
            'start-first;start-second;stop-only;stop-second;stop-first;',
            file_get_contents($this->directory . '/events'),
        );
        self::assertSame($services->get(Configuration::class), $application->config());
        $this->expectException(ApplicationStateException::class);
        $application->bootstrap();
    }

    public function testBootstrapHooksCanResolveConfiguredServices(): void
    {
        $this->write('config/services.global.php', <<<'PHP'
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;

return ['services' => ['ready' => new InstanceDefinition((object) ['value' => 'available'])]];
PHP);
        $module = $this->module('Reader', bootstrap: <<<'PHP'
file_put_contents(__DIR__ . '/../events', $services->get('ready')->value);
PHP);
        $application = new Application($this->directory . '/config');
        $application->registerModule($module);
        $application->bootstrap();

        self::assertSame('available', file_get_contents($this->directory . '/events'));
        $application->shutdown();
    }

    public function testBootstrapFailureCleansCompletedModulesAndPreservesCleanupFailures(): void
    {
        $first = $this->module('First', shutdown: $this->event('stop-first') . 'strlen([]);');
        $second = $this->module(
            'Second',
            bootstrap: $this->event('start-second'),
            shutdown: $this->event('stop-second') . "throw new RuntimeException('Cleanup failed');",
        );
        $broken = $this->module(
            'Broken',
            bootstrap: $this->event('start-broken') . "throw new RuntimeException('Startup failed');",
            shutdown: $this->event('must-not-stop-broken'),
        );
        $later = $this->module(
            'Later',
            bootstrap: $this->event('must-not-start'),
            shutdown: $this->event('must-not-stop'),
        );
        $application = new Application($this->directory . '/config');
        foreach ([$first, $second, $broken, $later] as $module) {
            $application->registerModule($module);
        }

        try {
            $application->bootstrap();
            self::fail('Expected a bootstrap hook failure.');
        } catch (ModuleBootstrapException $exception) {
            self::assertSame($broken, $exception->module);
            self::assertSame('Startup failed', $exception->getPrevious()->getMessage());
            self::assertSame([$second, $first], array_keys($exception->shutdownFailures));
            self::assertSame('Cleanup failed', $exception->shutdownFailures[$second]->getMessage());
            self::assertInstanceOf(TypeError::class, $exception->shutdownFailures[$first]);
        }

        $application->shutdown();
        self::assertSame(
            'start-second;start-broken;stop-second;stop-first;',
            file_get_contents($this->directory . '/events'),
        );
        $this->expectException(ApplicationStateException::class);
        $application->config();
    }

    public function testShutdownAttemptsEveryHookBeforeReportingAllFailures(): void
    {
        $first = $this->module('First', shutdown: $this->event('first'));
        $second = $this->module(
            'Second',
            shutdown: $this->event('second') . "throw new RuntimeException('Second failed');",
        );
        $third = $this->module('Third', shutdown: $this->event('third') . 'strlen([]);');
        $application = new Application($this->directory . '/config');
        foreach ([$first, $second, $third] as $module) {
            $application->registerModule($module);
        }
        $application->bootstrap();

        try {
            $application->shutdown();
            self::fail('Expected aggregated shutdown failures.');
        } catch (ModuleShutdownException $exception) {
            self::assertSame([$third, $second], array_keys($exception->failures));
            self::assertSame($exception->failures[$third], $exception->getPrevious());
            self::assertInstanceOf(TypeError::class, $exception->getPrevious());
            self::assertSame('Second failed', $exception->failures[$second]->getMessage());
        }

        $application->shutdown();
        self::assertSame('third;second;first;', file_get_contents($this->directory . '/events'));
    }

    public function testBootstrapEngineErrorsTriggerCleanupWithoutReplacingTheirCause(): void
    {
        $application = new Application($this->directory . '/config');
        $application->registerModule($this->module('Ready', shutdown: $this->event('cleaned')));
        $broken = $this->module('BrokenHook', bootstrap: 'strlen([]);');
        $application->registerModule($broken);

        try {
            $application->bootstrap();
            self::fail('Expected the bootstrap engine error to be translated.');
        } catch (ModuleBootstrapException $exception) {
            self::assertSame($broken, $exception->module);
            self::assertInstanceOf(TypeError::class, $exception->getPrevious());
            self::assertSame([], $exception->shutdownFailures);
        }

        self::assertSame('cleaned;', file_get_contents($this->directory . '/events'));
        $application->shutdown();
        $this->expectException(ApplicationStateException::class);
        $application->bootstrap();
    }

    public function testRejectsReentrantLifecycleCallsFromBootstrapAndShutdownHooks(): void
    {
        $application = new Application($this->directory . '/config');
        $module = $this->module('SuspendedHooks', bootstrap: 'Fiber::suspend();', shutdown: 'Fiber::suspend();');
        $application->registerModule($module);
        $bootstrap = new Fiber($application->bootstrap(...));
        $bootstrap->start();

        foreach (['bootstrap', 'shutdown', 'config'] as $method) {
            try {
                $application->$method();
                self::fail('Expected the bootstrap hook to keep the application unavailable.');
            } catch (ApplicationStateException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $bootstrap->resume();
        $shutdown = new Fiber($application->shutdown(...));
        $shutdown->start();
        foreach (['bootstrap', 'shutdown'] as $method) {
            try {
                $application->$method();
                self::fail('Expected the shutdown hook to prevent reentrant lifecycle calls.');
            } catch (ApplicationStateException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        $shutdown->resume();
        $application->shutdown();
    }

    private function event(string $message): string
    {
        return 'file_put_contents(__DIR__ . "/../events", ' . var_export($message . ';', true) . ', FILE_APPEND);';
    }

    private function write(string $path, string $body): void
    {
        file_put_contents($this->directory . '/' . $path, "<?php\n\ndeclare(strict_types=1);\n\n" . $body);
    }

    /** @return class-string<Module> */
    private function module(
        string $name,
        string $constructor = '',
        ?string $bootstrap = null,
        ?string $shutdown = null,
    ): string
    {
        mkdir($this->directory . '/' . $name);
        mkdir($this->directory . '/' . $name . '/config');
        $class = 'ApplicationTestModule' . bin2hex(random_bytes(8));
        $label = var_export($name, true);
        $log = var_export($this->directory . '/constructed', true);
        $capabilities = '';
        $imports = '';
        $hooks = '';
        foreach (['bootstrap' => $bootstrap, 'shutdown' => $shutdown] as $method => $body) {
            if ($body === null) {
                continue;
            }
            $capability = $method === 'bootstrap' ? 'BootstrapModule' : 'ShutdownModule';
            $capabilities .= ', ' . $capability;
            $imports .= 'use ExtendsSoftware\\ExaPHP\\Application\\Module\\' . $capability . ";\n";
            $hooks .= <<<PHP

    /**
     * Executes the test lifecycle operation.
     *
     * @param ServiceLocator \$services The application services.
     *
     * @return void
     */
    public function $method(ServiceLocator \$services): void
    {
        $body
    }

PHP;
        }
        $this->write($name . '/Module.php', <<<PHP
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
$imports
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

/** Provides configuration for an application integration test. */
final class $class implements Module, ConfigurableModule$capabilities
{
    /** The locator retained by a lifecycle hook for identity checks. */
    private ?ServiceLocator \$hookServices = null;

$hooks
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
