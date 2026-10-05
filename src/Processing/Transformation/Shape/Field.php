<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Shape;

use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\AmbiguousProcessingStepException;
use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\StepTransformer;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;

/**
 * Describes processing and presence requirements for one shape field.
 */
final readonly class Field
{
    /**
     * The adapter used for present field values, including null.
     *
     * @var StepTransformer
     */
    public StepTransformer $processor;

    /**
     * Creates a field that is required unless explicitly marked optional.
     *
     * @param Transformer<mixed, mixed>|Validator<mixed>|Pipeline $step The processing behavior for present values.
     * @param FieldPresence $presence The missing-field policy.
     *
     * @throws AmbiguousProcessingStepException When the step implements multiple roles.
     */
    public function __construct(
        Transformer|Validator|Pipeline $step,
        public FieldPresence $presence = FieldPresence::Required,
    ) {
        $this->processor = new StepTransformer($step);
    }
}
