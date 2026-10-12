<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation;

use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\SuccessfulProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\AmbiguousProcessingStepException;
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
        self::assertSame('value', $outer->process(' value ')->value());
    }

    public function testTransformerResultsArePreserved(): void
    {
        $result = new SuccessfulProcessingResult(null);
        $step = $this->createMock(Transformer::class);
        $step->expects(self::once())->method('transform')->with('input')->willReturn($result);
        self::assertSame($result, new StepTransformer($step)->transform('input'));
    }

    /**
     * @param list<class-string> $roles
     */
    #[DataProvider('roles')]
    public function testRejectsEveryMultipleRoleCombination(array $roles): void
    {
        $step = self::createStubForIntersectionOfInterfaces($roles);
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
        $step = self::createStubForIntersectionOfInterfaces([Transformer::class, Validator::class]);
        $this->expectException(AmbiguousProcessingStepException::class);
        new Field($step);
    }

    public function testCollectionRejectsAmbiguousStep(): void
    {
        $step = self::createStubForIntersectionOfInterfaces([Transformer::class, Validator::class]);
        $this->expectException(AmbiguousProcessingStepException::class);
        new EachItem($step);
    }

    public function testShapeExecutionExceptionsStopSubsequentFields(): void
    {
        $failure = new TypeError('Unexpected');
        $first = $this->createMock(Validator::class);
        $first->expects(self::once())->method('validate')->willThrowException($failure);
        $later = $this->createMock(Validator::class);
        $later->expects(self::never())->method('validate');
        $shape = new ArrayShape(['first' => new Field($first), 'later' => new Field($later)]);
        try {
            $shape->transform(['first' => 1, 'later' => 2]);
            self::fail('Expected exception.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testCollectionStopsOnExecutionException(): void
    {
        $failure = new TypeError('Unexpected');
        $step = $this->createMock(Validator::class);
        $step->expects(self::once())->method('validate')->willThrowException($failure);
        try {
            new EachItem($step)->transform([1, 2]);
            self::fail('Expected exception.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
