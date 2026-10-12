<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\AllOf;
use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\InvalidIntegerRangeException;
use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use TypeError;

final class AllOfTest extends TestCase
{
    public function testEmptyCompositeSucceeds(): void
    {
        $result = new AllOf()->validate(null);
        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testAllSuccessfulValidatorsProduceValidResult(): void
    {
        $result = new AllOf(new NotNull(), new IntegerRange(1, 100))->validate(42);
        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testEvaluatesEveryValidatorInOrderWithOriginalInputAndPreservesViolations(): void
    {
        $value = new stdClass();
        $firstViolation = new Violation('first', 'First', ['field'], ['limit' => 1]);
        $secondViolation = new Violation('second', 'Second');
        $calls = [];
        $first = $this->createMock(Validator::class);
        $first->expects(self::exactly(2))->method('validate')->with(self::identicalTo($value))
            ->willReturnCallback(static function () use (&$calls, $firstViolation, $secondViolation): ValidationResult {
                $calls[] = 'first';

                return new ValidationResult($firstViolation, $secondViolation);
            });
        $second = $this->createMock(Validator::class);
        $second->expects(self::once())->method('validate')->with(self::identicalTo($value))
            ->willReturnCallback(static function () use (&$calls): ValidationResult {
                $calls[] = 'second';

                return new ValidationResult();
            });

        $result = new AllOf($first, $second, $first)->validate($value);

        self::assertFalse($result->isValid());
        self::assertSame(['first', 'second', 'first'], $calls);
        self::assertSame(
            [$firstViolation, $secondViolation, $firstViolation, $secondViolation],
            $result->violations(),
        );
    }

    public function testNestedCompositesAndRepeatedCallsDoNotRetainPreviousViolations(): void
    {
        $validator = new AllOf(new AllOf(new NotNull()), new IntegerRange(1, 10));
        self::assertCount(2, $validator->validate(null)->violations());
        self::assertTrue($validator->validate(5)->isValid());
    }

    public function testPipelineStopsAfterCollectingCompositeViolations(): void
    {
        $later = $this->createMock(Transformer::class);
        $later->expects(self::never())->method('transform');
        $pipeline = new SequentialPipeline();
        $pipeline->append(new AllOf(new NotNull(), new IntegerRange(1, 10)));
        $pipeline->append($later);
        $result = $pipeline->process(null);
        self::assertFalse($result->isValid());
        self::assertCount(2, $result->violations());
    }

    #[DataProvider('failures')]
    public function testExecutionFailureStopsEvaluationAndPropagatesUnchanged(Throwable $failure): void
    {
        $first = $this->createMock(Validator::class);
        $first->expects(self::once())->method('validate')->willThrowException($failure);
        $later = $this->createMock(Validator::class);
        $later->expects(self::never())->method('validate');
        try {
            new AllOf($first, $later)->validate('input');
            self::fail('Expected execution failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * @return iterable<array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield [new InvalidIntegerRangeException('Invalid configuration')];
        yield [new TypeError('Unexpected error')];
    }
}
