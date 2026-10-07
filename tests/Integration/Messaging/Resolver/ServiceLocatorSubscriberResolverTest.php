<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Messaging\Resolver;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Exception\InvalidSubscriberMappingException;
use ExtendsSoftware\ExaPHP\Integration\Messaging\Resolver\ServiceLocatorSubscriberResolver;
use ExtendsSoftware\ExaPHP\Messaging\MessagingException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\Exception\SubscriberResolutionException;
use ExtendsSoftware\ExaPHP\Messaging\Subscription\MessageSubscriber;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class ServiceLocatorSubscriberResolverTest extends TestCase
{
    public function testConstructionDoesNotResolveMappedServices(): void
    {
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->never())->method('get');
        $services->expects($this->never())->method('has');

        new ServiceLocatorSubscriberResolver($services, ['notifications' => 'email-handler', 'unused' => 'missing']);
    }

    public function testResolvesOnlyTheMappedServiceWithoutInvokingIt(): void
    {
        $subscriber = $this->createMock(MessageSubscriber::class);
        $subscriber->expects($this->never())->method('handle');
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with('email-handler')->willReturn($subscriber);
        $resolver = new ServiceLocatorSubscriberResolver($services, [
            'notifications' => 'email-handler', 'unused' => 'missing',
        ]);

        self::assertSame($subscriber, $resolver->resolve('notifications'));
    }

    public function testAllowsNumericIdentifiersAndMultipleSubscribersSharingAService(): void
    {
        $subscriber = $this->createStub(MessageSubscriber::class);
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->exactly(2))->method('get')->with('handler')->willReturn($subscriber);
        $resolver = new ServiceLocatorSubscriberResolver($services, ['0' => 'handler', 'other' => 'handler']);

        self::assertSame($subscriber, $resolver->resolve('0'));
        self::assertSame($subscriber, $resolver->resolve('other'));
    }

    #[DataProvider('unmappedIdentifiers')]
    public function testUnmappedIdentifiersNeverFallBackToServiceLookup(string $id): void
    {
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->never())->method('get');
        $resolver = new ServiceLocatorSubscriberResolver($services, ['notifications' => 'handler']);

        $this->expectException(SubscriberResolutionException::class);

        $resolver->resolve($id);
    }

    /** @return iterable<array{string}> */
    public static function unmappedIdentifiers(): iterable
    {
        yield ['unknown'];
        yield ['Notifications'];
        yield [' notifications'];
        yield ['handler'];
        yield [''];
    }

    public function testEmptyMappingRejectsResolution(): void
    {
        $resolver = new ServiceLocatorSubscriberResolver($this->createStub(ServiceLocator::class), []);
        $this->expectException(SubscriberResolutionException::class);

        $resolver->resolve('notifications');
    }

    /** @param array<array-key, mixed> $mapping */
    #[DataProvider('invalidMappings')]
    public function testRejectsInvalidMappingsWithoutResolvingServices(array $mapping): void
    {
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->never())->method('get');
        try {
            new ServiceLocatorSubscriberResolver($services, $mapping);
            self::fail('Expected invalid mapping.');
        } catch (InvalidSubscriberMappingException $exception) {
            self::assertInstanceOf(IntegrationException::class, $exception);
        }
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidMappings(): iterable
    {
        yield 'empty subscriber' => [['' => 'handler']];
        yield 'empty service' => [['subscriber' => '']];
        yield 'integer service' => [['subscriber' => 1]];
        yield 'null service' => [['subscriber' => null]];
        yield 'object service' => [['subscriber' => new stdClass()]];
    }

    public function testRejectsServiceThatIsNotASubscriber(): void
    {
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willReturn(new stdClass());
        $resolver = new ServiceLocatorSubscriberResolver($services, ['notifications' => 'handler']);
        $this->expectException(SubscriberResolutionException::class);
        $this->expectExceptionMessageIs(
            'Service "handler" for subscriber "notifications" must implement MessageSubscriber.',
        );

        $resolver->resolve('notifications');
    }

    public function testTranslatesLookupFailuresWithContextAndPreservesTheCause(): void
    {
        $failure = new ServiceNotFoundException('Unknown service.');
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willThrowException($failure);
        $resolver = new ServiceLocatorSubscriberResolver($services, ['notifications' => 'handler']);
        try {
            $resolver->resolve('notifications');
            self::fail('Expected resolution failure.');
        } catch (SubscriberResolutionException $exception) {
            self::assertInstanceOf(MessagingException::class, $exception);
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringContainsString('notifications', $exception->getMessage());
            self::assertStringContainsString('handler', $exception->getMessage());
        }
    }

    public function testUnexpectedEngineErrorsPropagateUnchanged(): void
    {
        $failure = new TypeError('Invalid service factory.');
        $services = $this->createStub(ServiceLocator::class);
        $services->method('get')->willThrowException($failure);
        $resolver = new ServiceLocatorSubscriberResolver($services, ['notifications' => 'handler']);
        try {
            $resolver->resolve('notifications');
            self::fail('Expected engine error.');
        } catch (TypeError $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
