<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Application\Configuration;

use ErrorException;
use ExtendsSoftware\ExaPHP\Application\ApplicationException;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationLoader;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ConfigurationLoadException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\DuplicateServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidConfigurationException;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use FilesystemIterator;
use ParseError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use TypeError;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sys_get_temp_dir;
use function unlink;

final class ConfigurationLoaderIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/exa-config-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/module');
        mkdir($this->directory . '/application');
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

    public function testLoadsModuleThenGlobalThenLocalFilesAlphabetically(): void
    {
        $this->write('module/z.php', "return ['settings' => ['module' => 'z', 'global' => 'module']];");
        $this->write('module/a.php', "return ['settings' => ['module' => 'a', 'retained' => true]];");
        $this->write('application/z.global.php', "return ['settings' => ['global' => 'z', 'local' => 'global']];");
        $this->write('application/a.global.php', "return ['settings' => ['global' => 'a']];");
        $this->write('application/b.local.php', "return ['settings' => ['local' => 'b']];");
        $this->write('application/a.local.php', "return ['settings' => ['local' => 'a']];");

        $configuration = $this->loader()->load([$this->module()], $this->directory . '/application');

        self::assertSame(
            ['module' => 'z', 'retained' => true, 'global' => 'z', 'local' => 'b'],
            $configuration->get('settings'),
        );
    }

    public function testSkipsTemplatesUnrelatedFilesAndNestedDirectories(): void
    {
        foreach (['module/template.php.dist', 'application/test.local.php.dist', 'application/ignored.php'] as $file) {
            $this->write($file, "throw new RuntimeException('Must not execute');");
        }

        mkdir($this->directory . '/module/nested.php');
        mkdir($this->directory . '/application/nested.global.php');
        $this->write('module/nested.php/ignored.php', "throw new RuntimeException('Must not execute');");
        $this->write('application/nested.global.php/test.local.php', "throw new RuntimeException('Must not execute');");
        $this->write('module/loaded.php', "return ['loaded' => true];");

        self::assertTrue($this->loader()->load([$this->module()], $this->directory . '/application')->get('loaded'));
    }

    public function testAllowsEmptyDirectoriesAndModulesWithoutConfiguration(): void
    {
        $shared = new class implements Module {};
        $configuration = $this->loader()->load([$shared, $this->module()], $this->directory . '/application');

        self::assertFalse($configuration->has('services'));
    }

    public function testUsesModuleRegistrationOrderForOrdinarySettings(): void
    {
        $this->write('module/settings.php', "return ['order' => 'first'];");
        $this->write('application/settings.php', "return ['order' => 'second'];");
        $first = $this->module();
        $second = $this->secondModule();

        self::assertSame(
            'second',
            $this->loader()->load([$first, $second], $this->directory . '/application')->get('order'),
        );
        self::assertSame(
            'first',
            $this->loader()->load([$second, $first], $this->directory . '/application')->get('order'),
        );
    }

    public function testAllowsServiceOverridesWithinAModuleAndFromApplicationFiles(): void
    {
        $this->write('module/a.php', "return ['services' => ['client' => (object) ['name' => 'a']]];");
        $this->write('module/z.php', "return ['services' => ['client' => (object) ['name' => 'z']]];");
        $loader = $this->loader();

        $configuration = $loader->load([$this->module()], $this->directory . '/application');
        self::assertSame('z', $configuration->get('services')['client']->name);

        $this->write(
            'application/test.global.php',
            "return ['services' => ['client' => (object) ['name' => 'global']]];",
        );
        $this->write(
            'application/test.local.php',
            "return ['services' => ['client' => (object) ['name' => 'local']]];",
        );

        $configuration = $loader->load([$this->module()], $this->directory . '/application');
        self::assertSame('local', $configuration->get('services')['client']->name);
    }

    public function testReportsServiceConflictsWithBothModuleNames(): void
    {
        $this->write('module/services.php', "return ['services' => ['client' => new stdClass()]];");
        $this->write('application/services.php', "return ['services' => ['client' => new stdClass()]];");
        $this->write('application/override.local.php', "return ['services' => ['client' => new stdClass()]];");
        $first = $this->module();
        $second = $this->secondModule();

        try {
            $this->loader()->load([$first, $second], $this->directory . '/application');
            self::fail('Expected a duplicate service definition exception.');
        } catch (DuplicateServiceDefinitionException $exception) {
            self::assertStringContainsString($first::class, $exception->getMessage());
            self::assertStringContainsString($second::class, $exception->getMessage());
            self::assertStringContainsString('client', $exception->getMessage());
        }
    }

    public function testRejectsDuplicateModuleClasses(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loader()->load([$this->module(), $this->module()], $this->directory . '/application');
    }

    #[DataProvider('invalidFiles')]
    public function testReportsInvalidConfigurationWithItsFilePath(string $body): void
    {
        $path = $this->write('module/invalid.php', $body);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($path);

        $this->loader()->load([$this->module()], $this->directory . '/application');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFiles(): iterable
    {
        yield 'missing return' => [''];
        yield 'null return' => ['return null;'];
        yield 'object return' => ['return new stdClass();'];
        yield 'invalid services' => ["return ['services' => false];"];
    }

    #[DataProvider('failingFiles')]
    public function testPreservesExecutionFailureCausesAndRestoresTheErrorHandler(string $body, string $cause): void
    {
        $path = $this->write('application/failure.global.php', $body);
        $handler = static fn(): bool => false;
        set_error_handler($handler);

        try {
            try {
                $this->loader()->load([], $this->directory . '/application');
                self::fail('Expected a configuration load exception.');
            } catch (ConfigurationLoadException $exception) {
                self::assertInstanceOf(ApplicationException::class, $exception);
                self::assertInstanceOf($cause, $exception->getPrevious());
                self::assertStringContainsString($path, $exception->getMessage());
            }

            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /** @return iterable<string, array{string, class-string}> */
    public static function failingFiles(): iterable
    {
        yield 'exception' => ["throw new RuntimeException('Failure');", RuntimeException::class];
        yield 'engine error' => ['strlen([]);', TypeError::class];
        yield 'syntax error' => ['return [;', ParseError::class];
        yield 'warning' => ["trigger_error('Failure', E_USER_WARNING);", ErrorException::class];
    }

    public function testReportsMissingDirectories(): void
    {
        $this->expectException(ConfigurationLoadException::class);
        $this->expectExceptionMessage($this->directory . '/missing');

        $this->loader()->load([], $this->directory . '/missing');
    }

    public function testReportsMissingModuleDirectories(): void
    {
        rmdir($this->directory . '/module');
        $this->expectException(ConfigurationLoadException::class);
        $this->expectExceptionMessage($this->directory . '/module');

        $this->loader()->load([$this->module()], $this->directory . '/application');
    }

    public function testReloadsFilesAndDoesNotExposeLoaderVariables(): void
    {
        $this->write(
            'application/test.global.php',
            "return ['isolated' => !isset(\$this) && !isset(\$modules) && !isset(\$configDirectory)];",
        );
        $loader = $this->loader();
        self::assertTrue($loader->load([], $this->directory . '/application')->get('isolated'));
        $this->write('application/test.global.php', "return ['updated' => true];");

        self::assertTrue($loader->load([], $this->directory . '/application')->get('updated'));
    }

    public function testPropagatesModuleDirectoryFailuresUnchanged(): void
    {
        $failure = new RuntimeException('Directory unavailable.');
        $module = new readonly class($failure) implements Module, ConfigurableModule {
            /**
             * Creates a module with a directory lookup failure.
             *
             * @param RuntimeException $failure The failure to propagate.
             */
            public function __construct(private RuntimeException $failure) {}

            /**
             * Reports a directory lookup failure.
             *
             * @return string The configuration directory.
             *
             * @throws RuntimeException Always.
             */
            public function configDirectory(): string
            {
                throw $this->failure;
            }
        };

        try {
            $this->loader()->load([$module], $this->directory . '/application');
            self::fail('Expected the module directory failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testRestoresThePreviousErrorHandlerAfterSuccessfulLoading(): void
    {
        $this->write('application/test.global.php', 'return [];');
        $handler = static fn(): bool => false;
        set_error_handler($handler);

        try {
            $this->loader()->load([], $this->directory . '/application');
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    public function testRejectsAnEmptyDirectoryInsteadOfReadingTheWorkingDirectory(): void
    {
        $this->expectException(ConfigurationLoadException::class);

        $this->loader()->load([], '');
    }

    public function testReportsAFileThatDisappearsAfterDiscovery(): void
    {
        $this->write('application/a.global.php', "unlink(__DIR__ . '/z.global.php'); return [];");
        $missing = $this->write('application/z.global.php', 'return [];');

        try {
            $this->loader()->load([], $this->directory . '/application');
            self::fail('Expected a configuration load exception.');
        } catch (ConfigurationLoadException $exception) {
            self::assertStringContainsString($missing, $exception->getMessage());
            self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
        }
    }

    private function loader(): ConfigurationLoader
    {
        return new ConfigurationLoader(new ConfigurationMerger());
    }

    private function write(string $path, string $body): string
    {
        $file = $this->directory . '/' . $path;
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $body);

        return $file;
    }

    private function module(): Module
    {
        return new readonly class($this->directory . '/module') implements Module, ConfigurableModule {
            /**
             * Creates a module using the supplied directory.
             *
             * @param string $directory The configuration directory.
             */
            public function __construct(private string $directory) {}

            /**
             * Returns the test configuration directory.
             *
             * @return string The configuration directory.
             */
            public function configDirectory(): string
            {
                return $this->directory;
            }
        };
    }

    private function secondModule(): Module
    {
        return new readonly class($this->directory . '/application') implements Module, ConfigurableModule {
            /**
             * Creates a second module using the supplied directory.
             *
             * @param string $directory The configuration directory.
             */
            public function __construct(private string $directory) {}

            /**
             * Returns the test configuration directory.
             *
             * @return string The configuration directory.
             */
            public function configDirectory(): string
            {
                return $this->directory;
            }
        };
    }
}
