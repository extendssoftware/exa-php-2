<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Configuration;

use ErrorException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ConfigurationLoadException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\DuplicateServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidConfigurationException;
use ExtendsSoftware\ExaPHP\Application\Module\ConfigurableModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use FilesystemIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ValueError;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function restore_error_handler;
use function set_error_handler;
use function sort;
use function sprintf;
use function str_ends_with;

use const E_USER_WARNING;
use const E_WARNING;
use const SORT_STRING;

/**
 * Loads module configuration followed by application global and local overrides.
 *
 * Discovery is non-recursive and files are sorted by name within each group. Files execute on every load in an
 * isolated scope and must return arrays. Warning and execution failures are translated with their causes preserved.
 */
final readonly class ConfigurationLoader
{
    /**
     * Creates a loader using the supplied merge policy.
     *
     * @param ConfigurationMerger $merger The merger for module files and application overrides.
     */
    public function __construct(private ConfigurationMerger $merger)
    {
    }

    /**
     * Loads configuration for instantiated modules and an application configuration directory.
     *
     * Module classes must be unique. Modules without the configuration capability are skipped. Configured directories
     * must exist; empty directories are valid. Application global files all precede local files.
     *
     * @param list<Module> $modules The modules in registration order.
     * @param non-empty-string $configDirectory The application configuration directory.
     *
     * @return Configuration The merged configuration.
     *
     * @throws ConfigurationLoadException When directory discovery or file execution fails.
     * @throws InvalidConfigurationException When modules repeat, a file returns a non-array, or services is invalid.
     * @throws DuplicateServiceDefinitionException When different modules define the same service identifier.
     * @throws Throwable When a module's configuration directory method fails, propagated unchanged.
     */
    public function load(array $modules, string $configDirectory): Configuration
    {
        $moduleConfigurations = [];
        $registered = [];
        foreach ($modules as $module) {
            $name = $module::class;
            if (array_key_exists($name, $registered)) {
                throw new InvalidConfigurationException(sprintf('Module "%s" is configured more than once.', $name));
            }

            $registered[$name] = true;
            if (!$module instanceof ConfigurableModule) {
                continue;
            }

            $directory = $module->configDirectory();
            $sources = $this->loadFiles($this->files($directory), '.php');
            $moduleConfigurations[$name] = $this->merger->mergeSources($sources);
        }

        $files = $this->files($configDirectory);
        $global = $this->loadFiles($files, '.global.php');
        $local = $this->loadFiles($files, '.local.php');

        return $this->merger->merge($moduleConfigurations, $global + $local);
    }

    /**
     * Discovers regular files in a directory in alphabetical order.
     *
     * @param string $directory The configuration directory.
     *
     * @return list<string> The sorted file paths.
     *
     * @throws ConfigurationLoadException When the directory cannot be read.
     */
    private function files(string $directory): array
    {
        if ($directory === '') {
            throw new ConfigurationLoadException('Configuration directory must not be empty.');
        }

        try {
            $files = [];
            foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (RuntimeException | ValueError $exception) {
            throw new ConfigurationLoadException(
                sprintf('Could not read configuration directory "%s".', $directory),
                0,
                $exception,
            );
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Executes files matching an exact filename suffix.
     *
     * @param list<string> $files The sorted paths to inspect.
     * @param string $suffix The required filename suffix.
     *
     * @return array<string, array<array-key, mixed>> Configuration indexed by file path.
     *
     * @throws ConfigurationLoadException When a matching file fails to execute.
     * @throws InvalidConfigurationException When a matching file does not return an array.
     */
    private function loadFiles(array $files, string $suffix): array
    {
        $configurations = [];
        foreach ($files as $file) {
            if (str_ends_with($file, $suffix)) {
                $configurations[$file] = $this->loadFile($file);
            }
        }

        return $configurations;
    }

    /**
     * Executes a configuration file without exposing loader state to its local scope.
     *
     * A temporary error handler converts PHP warnings to ErrorException, which is wrapped in ConfigurationLoadException
     * with the original cause preserved. The previous handler is restored after success or failure.
     *
     * @param string $file The configuration file path.
     *
     * @return array<array-key, mixed> The returned configuration values.
     *
     * @throws ConfigurationLoadException When execution raises an exception, error, or warning.
     * @throws InvalidConfigurationException When the file returns a non-array value.
     */
    private function loadFile(string $file): array
    {
        set_error_handler(
            static function (int $severity, string $message, string $path, int $line): never {
                throw new ErrorException($message, 0, $severity, $path, $line);
            },
            E_WARNING | E_USER_WARNING,
        );

        try {
            $configuration = (static function (string $path): mixed {
                return require $path;
            })($file);
        } catch (Throwable $exception) {
            throw new ConfigurationLoadException(
                sprintf('Could not load configuration file "%s".', $file),
                0,
                $exception,
            );
        } finally {
            restore_error_handler();
        }

        if (!is_array($configuration)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Configuration file "%s" must return an array, %s returned.',
                    $file,
                    get_debug_type($configuration),
                ),
            );
        }

        return $configuration;
    }
}
