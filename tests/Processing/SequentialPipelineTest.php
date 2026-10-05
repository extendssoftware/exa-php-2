<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\AmbiguousPipelineStepException;
use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidIntegerRangeException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\NotBlank;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use TypeError;

final class SequentialPipelineTest extends TestCase
{
    public function testEmptyPipelinePreservesInputIncludingNull(): void
    {
        $pipeline = new SequentialPipeline();
        $object = new stdClass();
        $this->assertSame($object, $pipeline->process($object)->value());
        $result = $pipeline->process(null);
        $this->assertTrue($result->isValid());
        $this->assertNull($result->value());
    }

    #[DataProvider('inputs')]
    public function testBuiltInSteps(mixed $input, ?string $code): void
    {
        $pipeline = new SequentialPipeline();
        $pipeline->append(new TrimString());
        $pipeline->append(new NotBlank());
        $pipeline->append(new StringToInteger());
        $pipeline->append(new IntegerRange(1, 100));
        $result = $pipeline->process($input);
        $this->assertSame($code === null, $result->isValid());
        if ($code === null) {
            $this->assertSame(42, $result->value());
        } else {
            $this->assertSame($code, $result->violations()[0]->code());
        }
    }

    /**
     * @return iterable<array{mixed, ?string}>
     */
    public static function inputs(): iterable
    {
        yield [' 42 ', null];
        yield ['   ', NotBlank::CODE_BLANK_STRING];
        yield ['abc', StringToInteger::CODE_INVALID_INTEGER_FORMAT];
        yield ['101', IntegerRange::CODE_INTEGER_OUT_OF_RANGE];
        yield [null, TrimString::CODE_NOT_STRING];
    }

    public function testFailedTransformationStopsAndPreservesResult(): void
    {
        $failure = ProcessingResult::failure(new Violation('invalid', 'Invalid'));
        $transformer = $this->createMock(Transformer::class);
        $transformer->expects($this->once())->method('transform')->willReturn($failure);
        $later = $this->createMock(Validator::class);
        $later->expects($this->never())->method('validate');
        $pipeline = new SequentialPipeline();
        $pipeline->append($transformer);
        $pipeline->append($later);
        $this->assertSame($failure, $pipeline->process('input'));
    }

    public function testFailedValidationStopsAndPreservesAllViolations(): void
    {
        $first = new Violation('first', 'First', ['field']);
        $second = new Violation('second', 'Second');
        $validator = $this->createMock(Validator::class);
        $validator->expects($this->once())->method('validate')->willReturn(new ValidationResult($first, $second));
        $later = $this->createMock(Transformer::class);
        $later->expects($this->never())->method('transform');
        $pipeline = new SequentialPipeline();
        $pipeline->append($validator);
        $pipeline->append($later);
        $this->assertSame([$first, $second], $pipeline->process('input')->violations());
    }

    public function testRepeatedStepsAndCallsUseCurrentValue(): void
    {
        $step = $this->createMock(Transformer::class);
        $step->expects($this->exactly(4))->method('transform')->willReturnCallback(
            static fn(int $value): ProcessingResult => ProcessingResult::success($value + 1),
        );
        $pipeline = new SequentialPipeline();
        $pipeline->append($step);
        $pipeline->append($step);
        $this->assertSame(3, $pipeline->process(1)->value());
        $this->assertSame(12, $pipeline->process(10)->value());
    }

    public function testRejectsAmbiguousStepWithoutExecutingItOrChangingRegistrations(): void
    {
        $step = $this->createMockForIntersectionOfInterfaces([Transformer::class, Validator::class]);
        $step->expects($this->never())->method('transform');
        $step->expects($this->never())->method('validate');
        $pipeline = new SequentialPipeline();
        $pipeline->append(new TrimString());

        try {
            $pipeline->append($step);
            $this->fail('Expected ambiguous step rejection.');
        } catch (AmbiguousPipelineStepException $exception) {
            $this->assertSame(
                'A pipeline step must implement either Transformer or Validator, not both.',
                $exception->getMessage(),
            );
        }

        $pipeline->append(new StringToInteger());
        $this->assertSame(42, $pipeline->process(' 42 ')->value());
    }

    #[DataProvider('failures')]
    public function testExceptionsStopExecutionAndPropagateUnchanged(Throwable $failure): void
    {
        $step = $this->createMock(Validator::class);
        $step->expects($this->once())->method('validate')->willThrowException($failure);
        $later = $this->createMock(Transformer::class);
        $later->expects($this->never())->method('transform');
        $pipeline = new SequentialPipeline();
        $pipeline->append($step);
        $pipeline->append($later);
        try {
            $pipeline->process('input');
            $this->fail('Expected a failure.');
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
