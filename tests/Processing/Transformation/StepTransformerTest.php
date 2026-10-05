<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation;

use ExtendsSoftware\ExaPHP\Processing\Exception\AmbiguousProcessingStepException;
use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\ArrayShape;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\Field;
use ExtendsSoftware\ExaPHP\Processing\Transformation\StepTransformer;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class StepTransformerTest extends TestCase
{
    public function testPipelineCanBeNestedUsingAdapter(): void
    {
        $inner = new SequentialPipeline();
        $inner->append(new TrimString());
        $outer = new SequentialPipeline();
        $outer->append(new StepTransformer($inner));
        $this->assertSame('value', $outer->process(' value ')->value());
    }

    public function testTransformerResultsArePreserved(): void
    {
        $result = ProcessingResult::success(null);
        $step = $this->createMock(Transformer::class);
        $step->expects($this->once())->method('transform')->with('input')->willReturn($result);
        $this->assertSame($result, new StepTransformer($step)->transform('input'));
    }

    /**
     * @param list<class-string> $roles
     */
    #[DataProvider('roles')]
    public function testRejectsEveryMultipleRoleCombination(array $roles): void
    {
        $step = $this->createStubForIntersectionOfInterfaces($roles);
        $this->expectException(AmbiguousProcessingStepException::class);
        new StepTransformer($step);
    }

    /**
     * @return iterable<array{list<class-string>}>
     */
    public static function roles(): iterable
    {
        yield [[Transformer::class, Validator::class]];
        yield [[Transformer::class, Pipeline::class]];
        yield [[Validator::class, Pipeline::class]];
        yield [[Transformer::class, Validator::class, Pipeline::class]];
    }

    public function testFieldRejectsAmbiguousStep(): void
    {
        $step = $this->createStubForIntersectionOfInterfaces([Transformer::class, Validator::class]);
        $this->expectException(AmbiguousProcessingStepException::class);
        new Field($step);
    }

    public function testCollectionRejectsAmbiguousStep(): void
    {
        $step = $this->createStubForIntersectionOfInterfaces([Transformer::class, Validator::class]);
        $this->expectException(AmbiguousProcessingStepException::class);
        new EachItem($step);
    }

    public function testShapeExecutionExceptionsStopSubsequentFields(): void
    {
        $failure = new TypeError('Unexpected');
        $first = $this->createMock(Validator::class);
        $first->expects($this->once())->method('validate')->willThrowException($failure);
        $later = $this->createMock(Validator::class);
        $later->expects($this->never())->method('validate');
        $shape = new ArrayShape(['first' => new Field($first), 'later' => new Field($later)]);
        try {
            $shape->transform(['first' => 1, 'later' => 2]);
            $this->fail('Expected exception.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testCollectionStopsOnExecutionException(): void
    {
        $failure = new TypeError('Unexpected');
        $step = $this->createMock(Validator::class);
        $step->expects($this->once())->method('validate')->willThrowException($failure);
        try {
            new EachItem($step)->transform([1, 2]);
            $this->fail('Expected exception.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }
}
