<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Exception\AmbiguousPipelineStepException;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use Throwable;

/**
 * Executes transformation and validation steps sequentially until completion or failure.
 */
final class SequentialPipeline implements Pipeline
{
    /**
     * The steps in registration order.
     *
     * @var list<Transformer<mixed, mixed>|Validator<mixed>>
     */
    private array $steps = [];

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function append(Transformer|Validator $step): void
    {
        if ($step instanceof Transformer && $step instanceof Validator) {
            throw new AmbiguousPipelineStepException(
                'A pipeline step must implement either Transformer or Validator, not both.',
            );
        }

        $this->steps[] = $step;
    }

    /**
     * Processes the input through the registered steps, stopping at the first failure.
     *
     * Violations retain identity and order. No input cloning, exception translation, or rollback is performed.
     * Each call starts with its own value.
     *
     * @param mixed $value The initial input value.
     *
     * @return ProcessingResult<mixed> The final value or the first failed step's violations.
     *
     * @throws ProcessingException When a step reports a configuration or execution failure, propagated unchanged.
     * @throws Throwable When a step throws an unexpected exception or error, propagated unchanged.
     */
    #[Override]
    public function process(mixed $value): ProcessingResult
    {
        foreach ($this->steps as $step) {
            if ($step instanceof Transformer) {
                $result = $step->transform($value);
                if (!$result->isValid()) {
                    return $result;
                }
                $value = $result->value();
            } else {
                $result = $step->validate($value);
                if (!$result->isValid()) {
                    return ProcessingResult::failure(...$result->violations());
                }
            }
        }

        return ProcessingResult::success($value);
    }
}
