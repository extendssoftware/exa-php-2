<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\AmbiguousPipelineStepException;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;

/**
 * Processes a value through ordered transformation and validation steps.
 */
interface Pipeline
{
    /**
     * Appends a step to the execution order.
     *
     * Each step must implement exactly one role and accept the value produced by preceding steps.
     * Repeated registrations execute separately. Rejected steps must leave registrations unchanged.
     *
     * @param Transformer<mixed, mixed>|Validator<mixed> $step The transformation or validation step.
     *
     * @return void
     *
     * @throws AmbiguousPipelineStepException When the step implements both Transformer and Validator.
     * @throws ProcessingException When the step cannot be registered.
     */
    public function append(Transformer|Validator $step): void;

    /**
     * Runs steps in registration order, stopping at the first result containing violations.
     *
     * Successful transformations replace the current value; successful validation leaves it unchanged. A failed step's
     * violations are returned without executing subsequent steps. An empty pipeline succeeds with the original value.
     *
     * @param mixed $value The initial input, which must not be modified in place.
     *
     * @return ProcessingResult<mixed> The final value or the first unsuccessful step's violations.
     *
     * @throws ProcessingException When configuration or execution fails, stopping the pipeline.
     */
    public function process(mixed $value): ProcessingResult;
}
