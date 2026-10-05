<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cli;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Integration\Cli\CliModule;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CliModuleIntegrationTest extends TestCase
{
    public function testMergesNamedCommandsAndResolvesOnlySelectedHandler(): void
    {
        $created = 0;
        $handler = $this->createStub(CommandHandler::class);
        $handler->method('handle')->willReturn(23);
        $defaults = require new CliModule()->configDirectory() . '/services.php';
        $config = new ConfigurationMerger()->merge([
            'cli' => $defaults,
            'module' => ['cli' => ['commands' => [
                'greet' => new CommandDefinition('old', 'missing'),
                'unused' => new CommandDefinition('unused', 'missing'),
            ]]],
        ], ['application' => [
            'cli' => ['commands' => ['greet' => new CommandDefinition('greet', 'handler')]],
            'services' => ['handler' => new FactoryDefinition(static function () use (&$created, $handler) {
                ++$created;

                return $handler;
            })],
        ]]);
        $services = new ServiceLocatorFactory()->create($config);
        $dispatcher = $services->get(CommandDispatcher::class);
        $this->assertSame(0, $created);
        $this->assertSame('greet', $services->get(CommandRegistry::class)->get('greet')->name);
        $this->assertSame(23, $dispatcher->dispatch('greet', [], $this->createStub(Output::class)));
        $this->assertSame(1, $created);
        $this->assertSame($dispatcher, $services->get(CommandDispatcher::class));
    }

    public function testDefaultRegistryIsEmptyAndOutputUsesRuntimeStreams(): void
    {
        $defaults = require new CliModule()->configDirectory() . '/services.php';
        $services = new ServiceLocatorFactory()->create(new Configuration($defaults));
        $this->assertSame([], $services->get(CommandRegistry::class)->all());
        $this->assertInstanceOf(StreamOutput::class, $services->get(Output::class));
    }

    /** @param array<array-key, mixed> $overrides */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(array $overrides, string $service): void
    {
        $defaults = require new CliModule()->configDirectory() . '/services.php';
        $config = new ConfigurationMerger()->merge(['cli' => $defaults], ['application' => $overrides]);
        $services = new ServiceLocatorFactory()->create($config);
        try {
            $services->get($service);
            $this->fail('Expected invalid CLI configuration.');
        } catch (ServiceResolutionException $exception) {
            $this->assertInstanceOf(InvalidCliConfigurationException::class, $exception->getPrevious());
        }
    }

    /** @return iterable<array{array<array-key, mixed>, class-string}> */
    public static function invalidConfiguration(): iterable
    {
        yield [['cli' => null], CommandRegistry::class];
        yield [['cli' => ['commands' => null]], CommandRegistry::class];
        yield [['cli' => ['commands' => [new CommandDefinition('greet', 'handler')]]], CommandRegistry::class];
        yield [['cli' => ['commands' => ['greet' => 'handler']]], CommandRegistry::class];
    }
}
