<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator\Resolver;

use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\AliasDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ReflectionDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\ServiceDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceNotFoundException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\ServiceResolutionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnresolvableParameterException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\UnsupportedDefinitionException;
use ExtendsSoftware\ExaPHP\ServiceLocator\Resolver\ReflectionServiceResolver;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

use function count;

final class ReflectionServiceResolverTest extends TestCase
{
    public function testResolvesSelfRelativeToTheDeclaringClass(): void
    {
        $prototype = new readonly class {
            public function __construct(public ?self $dependency = null) {}
        };
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('has')->with($prototype::class)->willReturn(true);
        $locator->expects(self::once())->method('get')->with($prototype::class)->willReturn($prototype);

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertSame($prototype, $service->dependency);
    }

    public function testResolvesParentRelativeToTheDeclaringClass(): void
    {
        $prototype = new class extends stdClass {
            public function __construct(public ?parent $dependency = null) {}
        };
        $dependency = new stdClass();
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('has')->with(stdClass::class)->willReturn(true);
        $locator->expects(self::once())->method('get')->with(stdClass::class)->willReturn($dependency);

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertSame($dependency, $service->dependency);
    }

    public function testRejectsRequiredUnionParameters(): void
    {
        $prototype = new readonly class (new stdClass()) {
            public function __construct(public stdClass|ServiceLocator $dependency) {}
        };

        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches(
            '/\ACannot resolve constructor parameter \$dependency of "[^"]+"; use a factory definition\.\z/',
        );

        new ReflectionServiceResolver()->resolve(
            new ReflectionDefinition($prototype::class),
            $this->createStub(ServiceLocator::class),
        );
    }

    public function testWrapsConstructorFailuresWithTheirOriginalCause(): void
    {
        $prototype = new class {
            public function __construct(?RuntimeException $failure = null)
            {
                if ($failure !== null) {
                    throw $failure;
                }
            }
        };
        $failure = new RuntimeException('Constructor failed.');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('has')->with(RuntimeException::class)->willReturn(true);
        $locator->expects(self::once())->method('get')->with(RuntimeException::class)->willReturn($failure);

        try {
            new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);
            self::fail('Expected a service resolution failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testResolvesRequiredInterfaceDependenciesThroughTheLocator(): void
    {
        $dependency = $this->createStub(ServiceLocator::class);
        $prototype = new readonly class ($dependency) {
            public function __construct(public ServiceLocator $dependency) {}
        };
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('has');
        $locator->expects(self::once())->method('get')->with(ServiceLocator::class)->willReturn($dependency);

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertSame($dependency, $service->dependency);
    }

    public function testUsesDeclaredDefaultsWhenOptionalDependenciesAreNotRegistered(): void
    {
        $prototype = new readonly class {
            public function __construct(public ?stdClass $dependency = null, public string $label = 'default') {}
        };
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('has')->with(stdClass::class)->willReturn(false);
        $locator->expects(self::never())->method('get');

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertNull($service->dependency);
        self::assertSame('default', $service->label);
    }

    public function testRegisteredDependenciesTakePrecedenceOverDefaults(): void
    {
        $prototype = new readonly class {
            public function __construct(public ?stdClass $dependency = null) {}
        };
        $dependency = new stdClass();
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::once())->method('has')->with(stdClass::class)->willReturn(true);
        $locator->expects(self::once())->method('get')->with(stdClass::class)->willReturn($dependency);

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertSame($dependency, $service->dependency);
    }

    public function testRequiredNullableDependenciesStillRequireRegistration(): void
    {
        $prototype = new readonly class (null) {
            public function __construct(public ?stdClass $dependency) {}
        };
        $failure = new ServiceNotFoundException('Missing dependency.');
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('has');
        $locator->expects(self::once())->method('get')->with(stdClass::class)->willThrowException($failure);

        try {
            new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);
            self::fail('Expected the missing dependency failure.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testOmitsVariadicDependencies(): void
    {
        $prototype = new class {
            public int $dependencyCount;

            public function __construct(stdClass ...$dependencies)
            {
                $this->dependencyCount = count($dependencies);
            }
        };
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('has');
        $locator->expects(self::never())->method('get');

        $service = new ReflectionServiceResolver()->resolve(new ReflectionDefinition($prototype::class), $locator);

        self::assertSame(0, $service->dependencyCount);
    }

    public function testRejectsRequiredScalarParametersWithActionableContext(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageIs(
            'Cannot resolve constructor parameter $target of "' . AliasDefinition::class
            . '"; use a factory definition.',
        );

        new ReflectionServiceResolver()->resolve(
            new ReflectionDefinition(AliasDefinition::class),
            $this->createStub(ServiceLocator::class),
        );
    }

    public function testRejectsByReferenceParametersEvenWithDefaults(): void
    {
        $prototype = new class {
            public function __construct(?stdClass &$dependency = null) {}
        };

        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches(
            '/\ACannot resolve constructor parameter \$dependency of "[^"]+"; use a factory definition\.\z/',
        );

        new ReflectionServiceResolver()->resolve(
            new ReflectionDefinition($prototype::class),
            $this->createStub(ServiceLocator::class),
        );
    }

    public function testConstructsFreshInstancesWithoutConsultingTheLocator(): void
    {
        $resolver = new ReflectionServiceResolver();
        $definition = new ReflectionDefinition(stdClass::class);
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $first = $resolver->resolve($definition, $locator);
        $second = $resolver->resolve($definition, $locator);

        self::assertInstanceOf(stdClass::class, $first);
        self::assertInstanceOf(stdClass::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testWrapsMissingClassFailuresWithTheirOriginalCause(): void
    {
        $definition = new ReflectionDefinition('ExaPHP\\MissingService');

        try {
            new ReflectionServiceResolver()->resolve($definition, $this->createStub(ServiceLocator::class));
            self::fail('Expected a service resolution failure.');
        } catch (ServiceResolutionException $exception) {
            self::assertStringContainsString($definition->className, $exception->getMessage());
            self::assertInstanceOf(Throwable::class, $exception->getPrevious());
        }
    }

    public function testRejectsNonInstantiableClasses(): void
    {
        $this->expectException(ServiceResolutionException::class);
        $this->expectExceptionMessageIs(sprintf('Service class "%s" is not instantiable.', ServiceLocator::class));

        new ReflectionServiceResolver()->resolve(
            new ReflectionDefinition(ServiceLocator::class),
            $this->createStub(ServiceLocator::class),
        );
    }

    public function testSupportsOnlyItsDefinitionType(): void
    {
        $resolver = new ReflectionServiceResolver();

        self::assertTrue($resolver->supports(new ReflectionDefinition(stdClass::class)));
        self::assertFalse($resolver->supports($this->createStub(ServiceDefinition::class)));
    }

    public function testRejectsUnsupportedDefinitionsWithoutConsultingTheLocator(): void
    {
        $locator = $this->createMock(ServiceLocator::class);
        $locator->expects(self::never())->method('get');
        $locator->expects(self::never())->method('has');

        $this->expectException(UnsupportedDefinitionException::class);

        new ReflectionServiceResolver()->resolve($this->createStub(ServiceDefinition::class), $locator);
    }
}
