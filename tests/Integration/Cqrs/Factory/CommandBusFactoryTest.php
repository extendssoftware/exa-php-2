<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cqrs\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidCommandRegistrationException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Exception\InvalidCqrsConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory\CommandBusFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Fixture\ParentCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CommandBusFactoryTest extends TestCase
{
    public function testCreatesABusUsingConfiguredServiceIdentifiers(): void
    {
        $message = new ParentCommand();
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($message));
        $configuration = new Configuration([
            'cqrs' => ['commands' => [ParentCommand::class => 'handler']],
            'services' => [
                'handler' => new InstanceDefinition($handler),
                'bus' => new FactoryDefinition(new CommandBusFactory()->create(...)),
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        $locator->get('bus')->dispatch($message);
    }

    #[DataProvider('emptyConfigurations')]
    public function testCreatesIndependentEmptyBuses(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $factory = new CommandBusFactory();

        self::assertNotSame($factory->create($locator), $factory->create($locator));
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function emptyConfigurations(): iterable
    {
        yield [[]];
        yield [['cqrs' => []]];
        yield [['cqrs' => ['commands' => []]]];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $this->expectException(InvalidCqrsConfigurationException::class);
        new CommandBusFactory()->create($locator);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield [['cqrs' => null]];
        yield [['cqrs' => 'invalid']];
        yield [['cqrs' => ['commands' => null]]];
        yield [['cqrs' => ['commands' => 'invalid']]];
        yield [['cqrs' => ['commands' => ['handler']]]];
        yield [['cqrs' => ['commands' => ['' => 'handler']]]];
        yield [['cqrs' => ['commands' => [ParentCommand::class => '']]]];
        yield [['cqrs' => ['commands' => [ParentCommand::class => new stdClass()]]]];
    }

    public function testRejectsAnIncorrectConfigurationService(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidCqrsConfigurationException::class);
        new CommandBusFactory()->create($locator);
    }

    public function testPropagatesServiceResolutionFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing handler');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $id) use ($failure): object {
                if ($id === Configuration::class) {
                    return new Configuration(['cqrs' => ['commands' => [ParentCommand::class => 'missing']]]);
                }

                throw $failure;
            },
        );

        try {
            new CommandBusFactory()->create($locator);
            self::fail('Expected a service resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testBusRejectsAnInvalidResolvedHandler(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'cqrs' => ['commands' => [ParentCommand::class => 'handler']],
            'services' => ['handler' => new InstanceDefinition(new stdClass())],
        ]));
        $this->expectException(InvalidCommandRegistrationException::class);
        new CommandBusFactory()->create($locator);
    }
}
