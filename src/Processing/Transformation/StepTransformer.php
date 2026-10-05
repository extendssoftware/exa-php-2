<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation;

use ExtendsSoftware\ExaPHP\Processing\Exception\AmbiguousProcessingStepException;
use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use Throwable;

/**
 * Adapts a single processing role to a transformation result.
 *
 * @implements Transformer<mixed, mixed>
 */
final readonly class StepTransformer implements Transformer
{
    /**
     * Creates an adapter without executing the step.
     *
     * @param Transformer<mixed, mixed>|Validator<mixed>|Pipeline $step The step with exactly one processing role.
     *
     * @throws AmbiguousProcessingStepException When the step implements multiple roles.
     */
    public function __construct(private Transformer|Validator|Pipeline $step)
    {
        $roles = (int) ($step instanceof Transformer) + (int) ($step instanceof Validator)
            + (int) ($step instanceof Pipeline);
        if ($roles !== 1) {
            throw new AmbiguousProcessingStepException('A nested processing step must implement exactly one role.');
        }
    }

    /**
     * Executes the configured role, retaining the input after successful validation.
     *
     * @param mixed $value The input value.
     *
     * @return ProcessingResult<mixed> The processed value or unchanged violations.
     *
     * @throws Throwable When execution fails, propagated unchanged.
     */
    public function transform(mixed $value): ProcessingResult
    {
        if ($this->step instanceof Transformer) {
            return $this->step->transform($value);
        }
        if ($this->step instanceof Pipeline) {
            return $this->step->process($value);
        }
        $result = $this->step->validate($value);

        return $result->isValid()
            ? ProcessingResult::success($value)
            : ProcessingResult::failure(...$result->violations());
    }
}
