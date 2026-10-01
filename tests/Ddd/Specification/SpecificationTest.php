<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Ddd\Specification;

use ExtendsSoftware\ExaPHP\Ddd\Specification\AbstractSpecification;
use ExtendsSoftware\ExaPHP\Ddd\Specification\AndSpecification;
use ExtendsSoftware\ExaPHP\Ddd\Specification\NotSpecification;
use ExtendsSoftware\ExaPHP\Ddd\Specification\OrSpecification;
use ExtendsSoftware\ExaPHP\Ddd\Specification\Specification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

final class SpecificationTest extends TestCase
{
    #[DataProvider('combinations')]
    public function testBooleanComposition(bool $leftValue, bool $rightValue): void
    {
        $candidate = new stdClass();
        $left = $this->createStub(Specification::class);
        $left->method('isSatisfiedBy')->willReturn($leftValue);
        $right = $this->createStub(Specification::class);
        $right->method('isSatisfiedBy')->willReturn($rightValue);

        self::assertSame($leftValue && $rightValue, new AndSpecification($left, $right)->isSatisfiedBy($candidate));
        self::assertSame($leftValue || $rightValue, new OrSpecification($left, $right)->isSatisfiedBy($candidate));
        self::assertSame(!$leftValue, new NotSpecification($left)->isSatisfiedBy($candidate));
    }

    /**
     * @return iterable<array{bool, bool}>
     */
    public static function combinations(): iterable
    {
        yield [false, false];
        yield [false, true];
        yield [true, false];
        yield [true, true];
    }

    #[DataProvider('combinations')]
    public function testEvaluatesLeftFirstAndShortCircuits(bool $isAnd, bool $leftValue): void
    {
        $candidate = new stdClass();
        $calls = [];
        $left = $this->createMock(Specification::class);
        $left->expects(self::once())->method('isSatisfiedBy')->with(self::identicalTo($candidate))
            ->willReturnCallback(static function () use (&$calls, $leftValue): bool {
                $calls[] = 'left';
                return $leftValue;
            });
        $right = $this->createMock(Specification::class);
        $evaluateRight = $isAnd === $leftValue;
        $right->expects($evaluateRight ? self::once() : self::never())->method('isSatisfiedBy')
            ->with(self::identicalTo($candidate))->willReturnCallback(static function () use (&$calls): bool {
                $calls[] = 'right';
                return true;
            });
        $specification = $isAnd ? new AndSpecification($left, $right) : new OrSpecification($left, $right);

        self::assertSame([], $calls);
        $specification->isSatisfiedBy($candidate);
        self::assertSame($evaluateRight ? ['left', 'right'] : ['left'], $calls);
    }

    public function testFluentCompositionDoesNotEvaluateOrChangeTheOriginalSpecification(): void
    {
        $original = new class extends AbstractSpecification {
            public function isSatisfiedBy(object $candidate): bool
            {
                return true;
            }
        };
        $other = $this->createMock(Specification::class);
        $other->expects(self::never())->method('isSatisfiedBy');
        $combined = $original->not()->and($other)->or($original);

        self::assertNotSame($original, $combined);
        self::assertTrue($combined->isSatisfiedBy(new stdClass()));
        self::assertTrue($original->isSatisfiedBy(new stdClass()));
        self::assertFalse($original->not()->isSatisfiedBy(new stdClass()));
    }

    #[DataProvider('failures')]
    public function testPreservesEvaluationFailures(string $operator, Throwable $failure): void
    {
        $operand = $this->createMock(Specification::class);
        $operand->expects(self::once())->method('isSatisfiedBy')->willThrowException($failure);
        $other = $this->createMock(Specification::class);
        $other->expects(self::never())->method('isSatisfiedBy');
        $specification = match ($operator) {
            'and' => new AndSpecification($operand, $other),
            'or' => new OrSpecification($operand, $other),
            'not' => new NotSpecification($operand),
        };

        try {
            $specification->isSatisfiedBy(new stdClass());
            self::fail('Expected evaluation failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * @return iterable<array{string, Throwable}>
     */
    public static function failures(): iterable
    {
        foreach (['and', 'or', 'not'] as $operator) {
            yield [$operator, new RuntimeException('Domain failure')];
            yield [$operator, new TypeError('Evaluation error')];
        }
    }
}
