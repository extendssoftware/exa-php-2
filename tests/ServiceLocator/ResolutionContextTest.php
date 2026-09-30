<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\ServiceLocator;

use Error;
use ExtendsSoftware\ExaPHP\ServiceLocator\Exception\CircularDependencyException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ResolutionContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

final class ResolutionContextTest extends TestCase
{
    public function testReturnsCallbackResultsAndAllowsRepeatedResolution(): void
    {
        $context = new ResolutionContext();
        $first = new stdClass();
        $second = new stdClass();

        self::assertSame($first, $context->resolve('service', static fn (): object => $first));
        self::assertSame($second, $context->resolve('service', static fn (): object => $second));
    }

    public function testAllowsNestedResolutionOfDistinctIdentifiers(): void
    {
        $context = new ResolutionContext();
        $service = new stdClass();

        $result = $context->resolve('1', static fn (): object => $context->resolve(
            '01',
            static fn (): object => $service,
        ));

        self::assertSame($service, $result);
    }

    public function testRejectsDirectCyclesWithoutInvokingTheCallback(): void
    {
        $context = new ResolutionContext();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageIs('Circular service dependency: service -> service.');

        $context->resolve('service', static fn (): object => $context->resolve('service', static function (): object {
            self::fail('A circular resolution must not invoke its callback.');
        }));
    }

    public function testReportsTheFullCircularPathAndClearsItAfterFailure(): void
    {
        $context = new ResolutionContext();
        $service = new stdClass();

        try {
            $context->resolve('a', static fn (): object => $context->resolve(
                'b',
                static fn (): object => $context->resolve('a', static fn (): object => $service),
            ));
            self::fail('Expected a circular dependency failure.');
        } catch (CircularDependencyException $exception) {
            self::assertSame('Circular service dependency: a -> b -> a.', $exception->getMessage());
        }

        self::assertSame($service, $context->resolve('a', static fn (): object => $service));
        self::assertSame($service, $context->resolve('b', static fn (): object => $service));
    }

    public function testCompletedChildrenAndRejectedCyclesPreserveTheActiveParent(): void
    {
        $context = new ResolutionContext();
        $service = new stdClass();

        $context->resolve('parent', static function () use ($context, $service): object {
            self::assertSame($service, $context->resolve('child', static fn (): object => $service));
            self::assertSame($service, $context->resolve('child', static fn (): object => $service));

            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $context->resolve('parent', static fn (): object => $service);
                    self::fail('The parent must remain active until its callback completes.');
                } catch (CircularDependencyException $exception) {
                    self::assertSame('Circular service dependency: parent -> parent.', $exception->getMessage());
                }
            }

            return $service;
        });
    }

    #[DataProvider('callbackFailures')]
    public function testPropagatesFailuresUnchangedAndClearsTheNestedPath(Throwable $failure): void
    {
        $context = new ResolutionContext();
        $service = new stdClass();

        try {
            $context->resolve('parent', static fn (): object => $context->resolve(
                'child',
                static fn (): object => throw $failure,
            ));
            self::fail('Expected the callback failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame($service, $context->resolve('parent', static fn (): object => $service));
        self::assertSame($service, $context->resolve('child', static fn (): object => $service));
    }

    public static function callbackFailures(): iterable
    {
        yield 'exception' => [new RuntimeException('Resolution failed.')];
        yield 'error' => [new Error('Resolution failed.')];
    }

    public function testIndependentContextsCanResolveTheSameIdentifier(): void
    {
        $first = new ResolutionContext();
        $second = new ResolutionContext();
        $service = new stdClass();

        $result = $first->resolve('service', static fn (): object => $second->resolve(
            'service',
            static fn (): object => $service,
        ));

        self::assertSame($service, $result);
    }
}
