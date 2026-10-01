<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cqrs\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidQueryRegistrationException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Exception\InvalidCqrsConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Cqrs\Factory\QueryBusFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Query\Fixture\ParentQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class QueryBusFactoryTest extends TestCase
{
    public function testCreatesABusUsingConfiguredServiceIdentifiers(): void
    {
        $message = new ParentQuery();
        $handler = $this->createMock(QueryHandler::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($message))->willReturn('title');
        $configuration = new Configuration([
            'cqrs' => ['queries' => [ParentQuery::class => 'handler']],
            'services' => [
                'handler' => new InstanceDefinition($handler),
                'bus' => new FactoryDefinition(new QueryBusFactory()->create(...)),
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        self::assertSame('title', $locator->get('bus')->ask($message));
    }

    #[DataProvider('emptyConfigurations')]
    public function testCreatesIndependentEmptyBuses(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $factory = new QueryBusFactory();

        self::assertNotSame($factory->create($locator), $factory->create($locator));
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function emptyConfigurations(): iterable
    {
        yield [[]];
        yield [['cqrs' => []]];
        yield [['cqrs' => ['queries' => []]]];
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $this->expectException(InvalidCqrsConfigurationException::class);
        new QueryBusFactory()->create($locator);
    }

    /**
     * @return iterable<array{array<array-key, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield [['cqrs' => null]];
        yield [['cqrs' => 'invalid']];
        yield [['cqrs' => ['queries' => null]]];
        yield [['cqrs' => ['queries' => 'invalid']]];
        yield [['cqrs' => ['queries' => ['handler']]]];
        yield [['cqrs' => ['queries' => ['' => 'handler']]]];
        yield [['cqrs' => ['queries' => [ParentQuery::class => '']]]];
        yield [['cqrs' => ['queries' => [ParentQuery::class => new stdClass()]]]];
    }

    public function testRejectsAnIncorrectConfigurationService(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());
        $this->expectException(InvalidCqrsConfigurationException::class);
        new QueryBusFactory()->create($locator);
    }

    public function testPropagatesServiceResolutionFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing handler');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $id) use ($failure): object {
                if ($id === Configuration::class) {
                    return new Configuration(['cqrs' => ['queries' => [ParentQuery::class => 'missing']]]);
                }

                throw $failure;
            },
        );

        try {
            new QueryBusFactory()->create($locator);
            self::fail('Expected a service resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testBusRejectsAnInvalidResolvedHandler(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'cqrs' => ['queries' => [ParentQuery::class => 'handler']],
            'services' => ['handler' => new InstanceDefinition(new stdClass())],
        ]));
        $this->expectException(InvalidQueryRegistrationException::class);
        new QueryBusFactory()->create($locator);
    }
}
