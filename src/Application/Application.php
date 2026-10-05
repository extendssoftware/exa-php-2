<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationLoader;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Exception\ApplicationStateException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\DuplicateModuleException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\InvalidModuleException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleBootstrapException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleInstantiationException;
use ExtendsSoftware\ExaPHP\Application\Module\Exception\ModuleShutdownException;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Application\Module\BootstrapModule;
use ExtendsSoftware\ExaPHP\Application\Module\Module;
use ExtendsSoftware\ExaPHP\Application\Module\ShutdownModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function array_key_exists;
use function array_pop;
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
     * The current application lifecycle phase.
     */
    private ApplicationState $state = ApplicationState::Configuring;

    /**
     * Module instances that completed startup, retained in registration order until shutdown.
     *
     * @var list<Module>
     */
    private array $startedModules = [];

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
        if ($this->state !== ApplicationState::Configuring) {
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
     * Creates application services and runs module bootstrap hooks in registration order.
     *
     * Repeated calls while running return the same locator. Configuration is exposed after all hooks succeed.
     * Hook failure triggers reverse-order cleanup of modules that completed startup, preserving all failures.
     * Failed or stopped applications cannot restart, and registration closes when bootstrap begins.
     *
     * @return ServiceLocator The application service locator.
     *
     * @throws ApplicationStateException When bootstrap is in progress, previously failed, or shutdown has started.
     * @throws ModuleBootstrapException When a module bootstrap hook fails.
     * @throws ModuleInstantiationException When module construction raises a non-application exception or error.
     * @throws ApplicationException When module construction, configuration loading, or locator creation fails.
     * @throws Throwable When a module's configuration directory method fails, propagated unchanged.
     */
    public function bootstrap(): ServiceLocator
    {
        if ($this->state === ApplicationState::Running) {
            return $this->serviceLocator;
        }

        if ($this->state !== ApplicationState::Configuring) {
            throw new ApplicationStateException('Application bootstrap is not allowed in the current lifecycle phase.');
        }

        $this->state = ApplicationState::Bootstrapping;
        try {
            $modules = $this->instantiateModules();
            $configuration = $this->configurationLoader->load($modules, $this->configDirectory);
            $serviceLocator = $this->serviceLocatorFactory->create($configuration);
            $this->bootstrapModules($modules, $serviceLocator);
        } catch (Throwable $exception) {
            $this->state = ApplicationState::Failed;
            throw $exception;
        }

        $this->configuration = $configuration;
        $this->serviceLocator = $serviceLocator;
        $this->state = ApplicationState::Running;

        return $serviceLocator;
    }

    /**
     * Runs eligible module shutdown hooks once in reverse registration order.
     *
     * Every hook is attempted even after exceptions or engine errors. Repeated calls after shutdown or failed
     * bootstrap do nothing. Configuration remains available after a successfully bootstrapped application stops.
     *
     * @return void
     *
     * @throws ApplicationStateException When bootstrap has not started or a lifecycle operation is in progress.
     * @throws ModuleShutdownException When one or more shutdown hooks fail, after all hooks have been attempted.
     */
    public function shutdown(): void
    {
        if ($this->state === ApplicationState::Stopped || $this->state === ApplicationState::Failed) {
            return;
        }

        if ($this->state !== ApplicationState::Running) {
            throw new ApplicationStateException('Application shutdown requires completed bootstrap.');
        }

        $this->state = ApplicationState::ShuttingDown;
        $failures = $this->shutdownModules($this->serviceLocator);
        $this->state = ApplicationState::Stopped;

        if ($failures !== []) {
            throw new ModuleShutdownException($failures);
        }
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
     * Starts modules and records those eligible for shutdown.
     *
     * Modules without a bootstrap hook complete startup when reached. A failing module is responsible for its own
     * partially acquired resources; only previously completed modules are shut down automatically.
     *
     * @param list<Module> $modules The constructed modules in registration order.
     * @param ServiceLocator $services The application service locator.
     *
     * @return void
     *
     * @throws ModuleBootstrapException When a hook fails, with its original cause and any cleanup failures.
     */
    private function bootstrapModules(array $modules, ServiceLocator $services): void
    {
        foreach ($modules as $module) {
            if ($module instanceof BootstrapModule) {
                try {
                    $module->bootstrap($services);
                } catch (Throwable $exception) {
                    $this->state = ApplicationState::ShuttingDown;
                    $failures = $this->shutdownModules($services);
                    throw new ModuleBootstrapException($module::class, $exception, $failures);
                }
            }

            $this->startedModules[] = $module;
        }
    }

    /**
     * Attempts every eligible shutdown hook and collects its failure without interrupting cleanup.
     *
     * @param ServiceLocator $services The application service locator.
     *
     * @return array<class-string<Module>, Throwable> Hook failures indexed by module class in shutdown order.
     */
    private function shutdownModules(ServiceLocator $services): array
    {
        $failures = [];
        while (($module = array_pop($this->startedModules)) !== null) {
            if (!$module instanceof ShutdownModule) {
                continue;
            }

            try {
                $module->shutdown($services);
            } catch (Throwable $exception) {
                $failures[$module::class] = $exception;
            }
        }

        return $failures;
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
