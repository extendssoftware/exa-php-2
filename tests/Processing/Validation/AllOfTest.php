<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidIntegerRangeException;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\AllOf;
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
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
    }

    public function testAllSuccessfulValidatorsProduceValidResult(): void
    {
        $result = new AllOf(new NotNull(), new IntegerRange(1, 100))->validate(42);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
    }

    public function testEvaluatesEveryValidatorInOrderWithOriginalInputAndPreservesViolations(): void
    {
        $value = new stdClass();
        $firstViolation = new Violation('first', 'First', ['field'], ['limit' => 1]);
        $secondViolation = new Violation('second', 'Second');
        $calls = [];
        $first = $this->createMock(Validator::class);
        $first->expects($this->exactly(2))->method('validate')->with($this->identicalTo($value))
            ->willReturnCallback(static function () use (&$calls, $firstViolation, $secondViolation): ValidationResult {
                $calls[] = 'first';

                return new ValidationResult($firstViolation, $secondViolation);
            });
        $second = $this->createMock(Validator::class);
        $second->expects($this->once())->method('validate')->with($this->identicalTo($value))
            ->willReturnCallback(static function () use (&$calls): ValidationResult {
                $calls[] = 'second';

                return new ValidationResult();
            });

        $result = new AllOf($first, $second, $first)->validate($value);

        $this->assertFalse($result->isValid());
        $this->assertSame(['first', 'second', 'first'], $calls);
        $this->assertSame(
            [$firstViolation, $secondViolation, $firstViolation, $secondViolation],
            $result->violations(),
        );
    }

    public function testNestedCompositesAndRepeatedCallsDoNotRetainPreviousViolations(): void
    {
        $validator = new AllOf(new AllOf(new NotNull()), new IntegerRange(1, 10));
        $this->assertCount(2, $validator->validate(null)->violations());
        $this->assertTrue($validator->validate(5)->isValid());
    }

    public function testPipelineStopsAfterCollectingCompositeViolations(): void
    {
        $later = $this->createMock(Transformer::class);
        $later->expects($this->never())->method('transform');
        $pipeline = new SequentialPipeline();
        $pipeline->append(new AllOf(new NotNull(), new IntegerRange(1, 10)));
        $pipeline->append($later);
        $result = $pipeline->process(null);
        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->violations());
    }

    #[DataProvider('failures')]
    public function testExecutionFailureStopsEvaluationAndPropagatesUnchanged(Throwable $failure): void
    {
        $first = $this->createMock(Validator::class);
        $first->expects($this->once())->method('validate')->willThrowException($failure);
        $later = $this->createMock(Validator::class);
        $later->expects($this->never())->method('validate');
        try {
            new AllOf($first, $later)->validate('input');
            $this->fail('Expected execution failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
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
