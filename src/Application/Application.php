<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationLoader;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Exception\DuplicateModuleException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidModuleException;
use ExtendsSoftware\ExaPHP\Application\Exception\ModuleInstantiationException;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function array_key_exists;
use function sprintf;

/**
 * Bootstraps registered modules into application configuration and a shared service locator.
 */
final class Application
{
    /**
     * Module classes indexed by canonical class name in registration order.
     *
     * @var array<class-string<Module>, class-string<Module>>
     */
    private array $modules = [];

    /**
     * Whether bootstrap has started, permanently closing registration and preventing new bootstrap attempts.
     *
     * @var bool
     */
    private bool $bootstrapStarted = false;

    /**
     * The application configuration, available only after successful bootstrap.
     *
     * @var Configuration|null
     */
    private ?Configuration $configuration = null;

    /**
     * The service locator cached after successful bootstrap for subsequent calls.
     *
     * @var ServiceLocator|null
     */
    private ?ServiceLocator $serviceLocator = null;

    /**
     * Creates an application with configuration loading and locator construction collaborators.
     *
     * Default collaborators support construction with only the application configuration directory.
     *
     * @param non-empty-string $configDirectory The application configuration directory.
     * @param ConfigurationLoader $configurationLoader The module and application configuration loader.
     * @param ServiceLocatorFactory $serviceLocatorFactory The factory for the application service locator.
     */
    public function __construct(
        private readonly string $configDirectory,
        private readonly ConfigurationLoader $configurationLoader = new ConfigurationLoader(new ConfigurationMerger()),
        private readonly ServiceLocatorFactory $serviceLocatorFactory = new ServiceLocatorFactory(),
    ) {
    }

    /**
     * Registers a module class without instantiating it.
     *
     * Classes must implement Module and be instantiable without required constructor arguments.
     * Canonical class names identify duplicates, including differently cased names and class aliases.
     *
     * @param class-string<Module> $moduleClass The module class to register.
     *
     * @return void
     *
     * @throws ApplicationStateException When bootstrap has already started.
     * @throws InvalidModuleException When the class is missing or cannot be instantiated as a module without arguments.
     * @throws DuplicateModuleException When the module class is already registered.
     * @throws Throwable When class autoloading fails, propagated unchanged.
     */
    public function registerModule(string $moduleClass): void
    {
        if ($this->bootstrapStarted) {
            throw new ApplicationStateException('Modules cannot be registered after bootstrap has started.');
        }

        try {
            $class = new ReflectionClass($moduleClass);
        } catch (ReflectionException $exception) {
            throw new InvalidModuleException(sprintf('Module class "%s" does not exist.', $moduleClass), 0, $exception);
        }

        if (!$class->implementsInterface(Module::class) || !$class->isInstantiable()) {
            throw new InvalidModuleException(sprintf('Class "%s" must be an instantiable Module.', $moduleClass));
        }

        if (($class->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
            throw new InvalidModuleException(
                sprintf('Module "%s" must be constructible without arguments.', $moduleClass),
            );
        }

        $name = $class->getName();
        if (array_key_exists($name, $this->modules)) {
            throw new DuplicateModuleException(sprintf('Module "%s" is already registered.', $name));
        }

        $this->modules[$name] = $name;
    }

    /**
     * Instantiates modules, loads configuration, and creates the application service locator.
     *
     * Successful repeat calls return the same locator. Registration closes when bootstrap starts. Failed or reentrant
     * bootstrap calls cannot be retried on this instance; create a new application after correcting a failure.
     * Configuration is exposed only after every bootstrap step succeeds.
     *
     * @return ServiceLocator The application service locator.
     *
     * @throws ApplicationStateException When bootstrap is already in progress or a previous attempt failed.
     * @throws ModuleInstantiationException When module construction raises a non-application exception or error.
     * @throws ApplicationException When module construction, configuration loading, or locator creation fails.
     * @throws Throwable When a module's configuration directory method fails, propagated unchanged.
     */
    public function bootstrap(): ServiceLocator
    {
        if ($this->serviceLocator !== null) {
            return $this->serviceLocator;
        }

        if ($this->bootstrapStarted) {
            throw new ApplicationStateException('Application bootstrap is already in progress or previously failed.');
        }

        $this->bootstrapStarted = true;
        $modules = $this->instantiateModules();
        $configuration = $this->configurationLoader->load($modules, $this->configDirectory);
        $serviceLocator = $this->serviceLocatorFactory->create($configuration);

        $this->configuration = $configuration;
        $this->serviceLocator = $serviceLocator;

        return $serviceLocator;
    }

    /**
     * Returns the configuration used to bootstrap the application.
     *
     * @return Configuration The configuration registered in the application service locator.
     *
     * @throws ApplicationStateException When bootstrap has not completed successfully.
     */
    public function config(): Configuration
    {
        if ($this->configuration === null) {
            throw new ApplicationStateException(
                'Configuration is available only after successful application bootstrap.',
            );
        }

        return $this->configuration;
    }

    /**
     * Constructs modules in registration order.
     *
     * @return list<Module> The module instances.
     *
     * @throws ApplicationException When a module constructor reports an application failure, propagated unchanged.
     * @throws ModuleInstantiationException When another constructor exception or error occurs.
     */
    private function instantiateModules(): array
    {
        $modules = [];
        foreach ($this->modules as $moduleClass) {
            try {
                $modules[] = new $moduleClass();
            } catch (ApplicationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new ModuleInstantiationException(
                    sprintf('Could not instantiate module "%s".', $moduleClass),
                    0,
                    $exception,
                );
            }
        }

        return $modules;
    }
}
