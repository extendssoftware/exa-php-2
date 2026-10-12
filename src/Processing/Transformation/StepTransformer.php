<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation;

use ExtendsSoftware\ExaPHP\Processing\FailedProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SuccessfulProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\AmbiguousProcessingStepException;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use Override;
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
     * @param Pipeline|Transformer<mixed, mixed>|Validator<mixed> $step The step with exactly one processing role.
     *
     * @throws AmbiguousProcessingStepException When the step implements multiple roles.
     */
    public function __construct(private Pipeline|Transformer|Validator $step)
    {
        $roles = (int) ($step instanceof Transformer)
            + (int) ($step instanceof Validator)
            + (int) ($step instanceof Pipeline);
        if ($roles !== 1) {
            throw new AmbiguousProcessingStepException('A nested processing step must implement exactly one role.');
        }
    }

    /**
     * {@inheritDoc}
     *
     * Successful validation retains the input; reported violations are returned unchanged.
     *
     * @throws Throwable When execution fails, propagated unchanged.
     */
    #[Override]
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
            ? new SuccessfulProcessingResult($value)
            : new FailedProcessingResult(...$result->violations());
    }
}
