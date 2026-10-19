<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\JsonRpc\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory\MethodDispatcherFactory;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\Request;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\ResultResponse;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\InvalidMethodRegistrationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MethodDispatcherFactoryTest extends TestCase
{
    public function testResolvesConfiguredHandlerIdentifiersAndPreservesMethodNames(): void
    {
        $handler = new CallableMethodHandler(static fn(array|stdClass $params): mixed => $params);
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'jsonRpc' => ['methods' => ['example.echo' => 'handler', '123' => 'handler', '' => 'handler']],
            'services' => ['handler' => new InstanceDefinition($handler)],
        ]));
        $dispatcher = new MethodDispatcherFactory()->create($locator);

        foreach (['example.echo', '123', ''] as $name) {
            $response = $dispatcher->dispatch(new Request(1, $name, ['value']));
            self::assertInstanceOf(ResultResponse::class, $response);
            self::assertSame(['value'], $response->result);
        }
    }

    public function testResolvesAllHandlersBeforeDispatchWithoutExecutingThem(): void
    {
        $created = 0;
        $called = 0;
        $definition = new FactoryDefinition(static function () use (&$created, &$called): CallableMethodHandler {
            ++$created;

            return new CallableMethodHandler(static function () use (&$called): int {
                return ++$called;
            });
        });
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'jsonRpc' => ['methods' => ['first' => 'first.handler', 'second' => 'second.handler']],
            'services' => ['first.handler' => $definition, 'second.handler' => $definition],
        ]));

        $dispatcher = new MethodDispatcherFactory()->create($locator);

        self::assertSame(2, $created);
        self::assertSame(0, $called);
        self::assertSame(1, $dispatcher->dispatch(new Request(1, 'first'))->result);
        self::assertSame(2, $created);
    }

    /** @param array<array-key, mixed> $values */
    #[DataProvider('emptyConfigurations')]
    public function testCreatesIndependentEmptyDispatchers(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));
        $factory = new MethodDispatcherFactory();
        $dispatcher = $factory->create($locator);

        self::assertNotSame($dispatcher, $factory->create($locator));
        self::assertSame(-32601, $dispatcher->dispatch(new Request(1, 'unknown'))->error->code);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function emptyConfigurations(): iterable
    {
        yield [[]];
        yield [['jsonRpc' => []]];
        yield [['jsonRpc' => ['methods' => []]]];
    }

    /** @param array<array-key, mixed> $values */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsMalformedConfiguration(array $values): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration($values));

        $this->expectException(InvalidJsonRpcConfigurationException::class);
        new MethodDispatcherFactory()->create($locator);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        foreach ([null, false, 'invalid', new stdClass()] as $section) {
            yield [['jsonRpc' => $section]];
            yield [['jsonRpc' => ['methods' => $section]]];
        }
        foreach ([null, false, '', [], new stdClass()] as $id) {
            yield [['jsonRpc' => ['methods' => ['example' => $id]]]];
        }
    }

    public function testValidatesAllIdentifiersBeforeResolvingAnyHandler(): void
    {
        $created = 0;
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'jsonRpc' => ['methods' => ['first' => 'handler', 'invalid' => null]],
            'services' => ['handler' => new FactoryDefinition(
                static function () use (&$created): CallableMethodHandler {
                    ++$created;

                    return new CallableMethodHandler(static fn(): null => null);
                },
            )],
        ]));

        try {
            new MethodDispatcherFactory()->create($locator);
            self::fail('Expected malformed configuration to be rejected.');
        } catch (InvalidJsonRpcConfigurationException $exception) {
            self::assertInstanceOf(IntegrationException::class, $exception);
            self::assertSame(0, $created);
        }
    }

    public function testRejectsAnIncorrectConfigurationService(): void
    {
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willReturn(new stdClass());

        $this->expectException(InvalidJsonRpcConfigurationException::class);
        new MethodDispatcherFactory()->create($locator);
    }

    public function testPropagatesResolutionFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing method handler.');
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willReturnCallback(static function (string $id) use ($failure): object {
            if ($id === Configuration::class) {
                return new Configuration(['jsonRpc' => ['methods' => ['example' => 'missing']]]);
            }

            throw $failure;
        });

        try {
            new MethodDispatcherFactory()->create($locator);
            self::fail('Expected the original resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testDispatcherRejectsAnInvalidResolvedHandler(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'jsonRpc' => ['methods' => ['example' => 'handler']],
            'services' => ['handler' => new InstanceDefinition(new stdClass())],
        ]));

        $this->expectException(InvalidMethodRegistrationException::class);
        new MethodDispatcherFactory()->create($locator);
    }

    public function testDispatcherRejectsReservedMethodNames(): void
    {
        $locator = new ServiceLocatorFactory()->create(new Configuration([
            'jsonRpc' => ['methods' => ['rpc.example' => 'handler']],
            'services' => ['handler' => new InstanceDefinition(new CallableMethodHandler(static fn(): null => null))],
        ]));

        $this->expectException(InvalidMethodRegistrationException::class);
        new MethodDispatcherFactory()->create($locator);
    }
}
