<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator;

use Error;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\DefinitionServiceLocator;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\CircularDependencyException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\AliasServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\FactoryServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\InvokableServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\ServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

final class DefinitionServiceLocatorTest extends TestCase
{
    public function testHasChecksRegistrationWithoutConsultingResolvers(): void
    {
        $resolver = $this->createMock(ServiceResolver::class);
        $resolver->expects(self::never())->method('supports');
        $resolver->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator(['alias' => new AliasDefinition('missing')], [$resolver]);

        self::assertTrue($locator->has('alias'));
        self::assertFalse($locator->has('missing'));
    }

    public function testRejectsUnknownIdentifiersWithoutConsultingResolvers(): void
    {
        $resolver = $this->createMock(ServiceResolver::class);
        $resolver->expects(self::never())->method('supports');
        $resolver->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator([], [$resolver]);

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessageIs('Service "missing" is not registered.');

        $locator->get('missing');
    }

    public function testUsesTheFirstSupportingResolverAndCachesItsResult(): void
    {
        $definition = $this->createStub(ServiceDefinition::class);
        $service = new stdClass();
        $unsupported = $this->createMock(ServiceResolver::class);
        $unsupported->expects(self::once())->method('supports')->with($definition)->willReturn(false);
        $unsupported->expects(self::never())->method('resolve');
        $selected = $this->createMock(ServiceResolver::class);
        $selected->expects(self::once())->method('supports')->with($definition)->willReturn(true);
        $unused = $this->createMock(ServiceResolver::class);
        $unused->expects(self::never())->method('supports');
        $unused->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator(['service' => $definition], [$unsupported, $selected, $unused]);
        $selected->expects(self::once())->method('resolve')->with($definition, $locator)->willReturn($service);

        self::assertSame($service, $locator->get('service'));
        self::assertSame($service, $locator->get('service'));
    }

    public function testCachesServicesByIdentifierRatherThanDefinition(): void
    {
        $definition = new InvokableDefinition(stdClass::class);
        $locator = new DefinitionServiceLocator([
            'first' => $definition,
            'second' => $definition,
        ], [new InvokableServiceResolver()]);

        $first = $locator->get('first');
        $second = $locator->get('second');

        self::assertNotSame($first, $second);
        self::assertSame($first, $locator->get('first'));
        self::assertSame($second, $locator->get('second'));
    }

    public function testSharesServicesAcrossAliasesAndNestedFactoryResolutions(): void
    {
        $locator = new DefinitionServiceLocator([
            'service' => new InvokableDefinition(stdClass::class),
            'alias' => new AliasDefinition('service'),
            'dependent' => new FactoryDefinition(static fn (ServiceLocator $locator): object => (object) [
                'dependency' => $locator->get('alias'),
            ]),
        ], [new AliasServiceResolver(), new FactoryServiceResolver(), new InvokableServiceResolver()]);

        $dependent = $locator->get('dependent');

        self::assertSame($locator->get('service'), $dependent->dependency);
        self::assertSame($locator->get('service'), $locator->get('alias'));
        self::assertSame($dependent, $locator->get('dependent'));
    }

    public function testRejectsDefinitionsWithoutASupportingResolverAndAllowsRetry(): void
    {
        $definition = new AliasDefinition('target');
        $resolver = $this->createMock(ServiceResolver::class);
        $resolver->expects(self::exactly(2))->method('supports')->with($definition)->willReturn(false);
        $resolver->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator(['service' => $definition], [$resolver]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $locator->get('service');
                self::fail('Expected an unsupported definition failure.');
            } catch (UnsupportedDefinitionException $exception) {
                self::assertSame(
                    'No resolver supports service "service" (' . AliasDefinition::class . ').',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testRejectsRegisteredServicesWhenNoResolversAreConfigured(): void
    {
        $locator = new DefinitionServiceLocator(['service' => new AliasDefinition('target')], []);

        self::assertTrue($locator->has('service'));
        $this->expectException(UnsupportedDefinitionException::class);

        $locator->get('service');
    }

    #[DataProvider('resolverFailures')]
    public function testRetriesResolutionFailuresWithoutWrappingOrFallingBack(Throwable $failure): void
    {
        $definition = $this->createStub(ServiceDefinition::class);
        $service = new stdClass();
        $attempts = 0;
        $resolver = $this->createMock(ServiceResolver::class);
        $resolver->expects(self::exactly(2))->method('supports')->with($definition)->willReturn(true);
        $resolver->expects(self::exactly(2))->method('resolve')->willReturnCallback(
            static function () use (&$attempts, $failure, $service): object {
                if (++$attempts === 1) {
                    throw $failure;
                }

                return $service;
            },
        );
        $unused = $this->createMock(ServiceResolver::class);
        $unused->expects(self::never())->method('supports');
        $unused->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator(['service' => $definition], [$resolver, $unused]);

        try {
            $locator->get('service');
            self::fail('Expected the resolver failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame($service, $locator->get('service'));
        self::assertSame($service, $locator->get('service'));
    }

    #[DataProvider('resolverFailures')]
    public function testSupportFailuresPropagateUnchangedAndClearTheResolutionPath(Throwable $failure): void
    {
        $definition = $this->createStub(ServiceDefinition::class);
        $resolver = $this->createMock(ServiceResolver::class);
        $resolver->expects(self::exactly(2))->method('supports')->with($definition)->willThrowException($failure);
        $resolver->expects(self::never())->method('resolve');
        $unused = $this->createMock(ServiceResolver::class);
        $unused->expects(self::never())->method('supports');
        $unused->expects(self::never())->method('resolve');
        $locator = new DefinitionServiceLocator(['service' => $definition], [$resolver, $unused]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $locator->get('service');
                self::fail('Expected the support failure.');
            } catch (Throwable $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    public static function resolverFailures(): iterable
    {
        yield 'component exception' => [new ServiceResolutionException('Resolution failed.')];
        yield 'engine error' => [new Error('Resolution failed.')];
    }

    #[DataProvider('circularDefinitions')]
    public function testDetectsCyclesAndClearsTheResolutionPath(array $definitions, string $path): void
    {
        $locator = new DefinitionServiceLocator($definitions, [
            new AliasServiceResolver(),
            new FactoryServiceResolver(),
        ]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $locator->get('a');
                self::fail('Expected a circular dependency failure.');
            } catch (CircularDependencyException $exception) {
                self::assertSame('Circular service dependency: ' . $path . '.', $exception->getMessage());
            }
        }
    }

    public static function circularDefinitions(): iterable
    {
        yield 'direct alias' => [['a' => new AliasDefinition('a')], 'a -> a'];
        yield 'indirect aliases' => [[
            'a' => new AliasDefinition('b'),
            'b' => new AliasDefinition('a'),
        ], 'a -> b -> a'];
        yield 'factory dependency' => [[
            'a' => new FactoryDefinition(static fn (ServiceLocator $locator): object => $locator->get('b')),
            'b' => new AliasDefinition('a'),
        ], 'a -> b -> a'];
    }
}
