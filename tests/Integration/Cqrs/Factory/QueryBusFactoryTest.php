<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Cqrs\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Cqrs\Query\QueryHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\QueryMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidQueryRegistrationException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidQueryMiddlewareException;
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
            'cqrs' => ['query' => ['handlers' => [ParentQuery::class => 'handler']]],
            'services' => [
                'handler' => new InstanceDefinition($handler),
                'bus' => new FactoryDefinition(new QueryBusFactory()->create(...)),
            ],
        ]);
        $locator = new ServiceLocatorFactory()->create($configuration);

        self::assertSame('title', $locator->get('bus')->ask($message));
    }

    public function testResolvesConfiguredMiddlewareBeforeDispatch(): void
    {
        $middleware = $this->createMock(QueryMiddleware::class);
        $middleware->expects(self::once())->method('process')->willReturn('cached');
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'cqrs' => ['query' => ['middleware' => ['authorization']]],
            'services' => ['authorization' => new InstanceDefinition($middleware)],
        ]));
        self::assertSame('cached', new QueryBusFactory()->create($locator)->ask(new ParentQuery()));
    }

    public function testRejectsAResolvedServiceThatIsNotQueryMiddleware(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'cqrs' => ['query' => ['middleware' => ['invalid']]],
            'services' => ['invalid' => new InstanceDefinition(new stdClass())],
        ]));
        $this->expectException(InvalidQueryMiddlewareException::class);
        new QueryBusFactory()->create($locator);
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
        yield [['cqrs' => ['query' => []]]];
        yield [['cqrs' => ['query' => ['middleware' => []]]]];
        yield [['cqrs' => ['query' => ['handlers' => []]]]];
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
        yield [['cqrs' => ['query' => ['middleware' => null]]]];
        yield [['cqrs' => ['query' => ['middleware' => ['named' => 'middleware']]]]];
        yield [['cqrs' => ['query' => ['middleware' => ['']]]]];
        yield [['cqrs' => ['query' => ['middleware' => [new stdClass()]]]]];
        yield [['cqrs' => ['query' => null]]];
        yield [['cqrs' => ['query' => 'invalid']]];
        yield [['cqrs' => null]];
        yield [['cqrs' => 'invalid']];
        yield [['cqrs' => ['query' => ['handlers' => null]]]];
        yield [['cqrs' => ['query' => ['handlers' => 'invalid']]]];
        yield [['cqrs' => ['query' => ['handlers' => ['handler']]]]];
        yield [['cqrs' => ['query' => ['handlers' => ['' => 'handler']]]]];
        yield [['cqrs' => ['query' => ['handlers' => [ParentQuery::class => '']]]]];
        yield [['cqrs' => ['query' => ['handlers' => [ParentQuery::class => new stdClass()]]]]];
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
                    return new Configuration([
                        'cqrs' => ['query' => ['handlers' => [ParentQuery::class => 'missing']]],
                    ]);
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
            'cqrs' => ['query' => ['handlers' => [ParentQuery::class => 'handler']]],
            'services' => ['handler' => new InstanceDefinition(new stdClass())],
        ]));
        $this->expectException(InvalidQueryRegistrationException::class);
        new QueryBusFactory()->create($locator);
    }
}
