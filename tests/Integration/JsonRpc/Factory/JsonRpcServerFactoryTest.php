<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\JsonRpc\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception\InvalidJsonRpcConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\JsonRpc\Factory\JsonRpcServerFactory;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageDecoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Message\MessageEncoder;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\CallableMethodHandler;
use ExtendsSoftware\ExaPHP\JsonRpc\Method\RegisteredMethodDispatcher;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class JsonRpcServerFactoryTest extends TestCase
{
    public function testUsesRegisteredCollaboratorsWithoutExecutingAMethodDuringCreation(): void
    {
        $calls = 0;
        $dispatcher = new RegisteredMethodDispatcher([
            'value' => new CallableMethodHandler(static function () use (&$calls): int {
                return ++$calls;
            }),
        ]);
        $locator = new ServiceLocatorFactory()->create(new Configuration(['services' => [
            MessageDecoder::class => new InstanceDefinition(new MessageDecoder()),
            RegisteredMethodDispatcher::class => new InstanceDefinition($dispatcher),
            MessageEncoder::class => new InstanceDefinition(new MessageEncoder()),
        ]]));
        $factory = new JsonRpcServerFactory();

        $server = $factory->create($locator);

        self::assertNotSame($server, $factory->create($locator));
        self::assertSame(0, $calls);
        self::assertSame('{"jsonrpc":"2.0","result":1,"id":1}', $server->handle(
            '{"jsonrpc":"2.0","method":"value","id":1}',
        ));
    }

    #[DataProvider('collaboratorIds')]
    public function testRejectsIncompatibleCollaboratorServices(string $invalidId): void
    {
        $services = [
            MessageDecoder::class => new InstanceDefinition(new MessageDecoder()),
            RegisteredMethodDispatcher::class => new InstanceDefinition(new RegisteredMethodDispatcher()),
            MessageEncoder::class => new InstanceDefinition(new MessageEncoder()),
        ];
        $services[$invalidId] = new InstanceDefinition(new stdClass());
        $locator = new ServiceLocatorFactory()->create(new Configuration(['services' => $services]));

        $this->expectException(InvalidJsonRpcConfigurationException::class);
        new JsonRpcServerFactory()->create($locator);
    }

    /** @return iterable<array{string}> */
    public static function collaboratorIds(): iterable
    {
        yield [MessageDecoder::class];
        yield [RegisteredMethodDispatcher::class];
        yield [MessageEncoder::class];
    }

    public function testPropagatesServiceResolutionFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing decoder.');
        $locator = self::createStub(ServiceLocator::class);
        $locator->method('get')->willThrowException($failure);

        try {
            new JsonRpcServerFactory()->create($locator);
            self::fail('Expected the original resolution failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
