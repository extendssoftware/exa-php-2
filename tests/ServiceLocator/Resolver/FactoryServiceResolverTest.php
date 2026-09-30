<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Resolver;

use Error;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\FactoryServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

final class FactoryServiceResolverTest extends TestCase
{
    public function testPassesTheLocatorToTheFactoryAndDoesNotCacheResults(): void
    {
        $locator = $this->createStub(ServiceLocator::class);
        $definition = new FactoryDefinition(static function (ServiceLocator $supplied) use ($locator): object {
            self::assertSame($locator, $supplied);

            return new stdClass();
        });
        $resolver = new FactoryServiceResolver();

        self::assertNotSame($resolver->resolve($definition, $locator), $resolver->resolve($definition, $locator));
    }

    public function testReturnsTheExactFactoryResult(): void
    {
        $service = new stdClass();
        $definition = new FactoryDefinition(static fn (): object => $service);

        self::assertSame(
            $service,
            new FactoryServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class)),
        );
    }

    public function testPropagatesComponentFailuresUnchanged(): void
    {
        $failure = new ServiceNotFoundException('Missing dependency.');
        $definition = new FactoryDefinition(static fn (): object => throw $failure);

        try {
            new FactoryServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class));
            self::fail('Expected the component failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    #[DataProvider('factoryFailures')]
    public function testWrapsOtherFactoryFailuresWithTheirOriginalCause(Throwable $failure): void
    {
        $definition = new FactoryDefinition(static fn (): object => throw $failure);

        try {
            new FactoryServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class));
            self::fail('Expected a service resolution failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertSame('Service factory failed.', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public static function factoryFailures(): iterable
    {
        yield 'exception' => [new RuntimeException('Factory failed.')];
        yield 'engine error' => [new Error('Factory failed.')];
    }

    #[DataProvider('nonObjectResults')]
    public function testRejectsNonObjectFactoryResults(mixed $result): void
    {
        $definition = new FactoryDefinition(static fn (): mixed => $result);

        try {
            new FactoryServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class));
            self::fail('Expected a service resolution failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertInstanceOf(TypeError::class, $exception->getPrevious());
        }
    }

    public static function nonObjectResults(): iterable
    {
        yield 'null' => [null];
        yield 'boolean' => [false];
        yield 'integer' => [1];
        yield 'float' => [1.5];
        yield 'string' => ['service'];
        yield 'array' => [[]];
    }

    public function testSupportsOnlyItsDefinitionType(): void
    {
        $resolver = new FactoryServiceResolver();

        self::assertTrue($resolver->supports(new FactoryDefinition(static fn (): object => new stdClass())));
        self::assertFalse($resolver->supports($this->createStub(ServiceDefinition::class)));
    }

    public function testRejectsUnsupportedDefinitionsWithoutConsultingTheLocator(): void
    {
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $this->expectException(UnsupportedDefinitionException::class);

        new FactoryServiceResolver()->resolve($this->createStub(ServiceDefinition::class), $locator);
    }
}
