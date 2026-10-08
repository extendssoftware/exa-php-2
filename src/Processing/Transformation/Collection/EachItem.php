<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Collection;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\AmbiguousProcessingStepException;
use ExtendsSoftware\ExaPHP\Processing\Pipeline;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\StepTransformer;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use Throwable;

use function array_is_list;
use function is_array;

/**
 * Processes every array item, preserving keys and collecting prefixed violations.
 *
 * @implements Transformer<mixed, array<array-key, mixed>>
 */
final readonly class EachItem implements Transformer
{
    /**
     * The input is not an array.
     */
    public const string CODE_NOT_ARRAY = 'not_array';

    /**
     * The input does not have consecutive integer keys starting at zero.
     */
    public const string CODE_NOT_LIST = 'not_list';

    /**
     * The processor reused for every item.
     *
     * @var StepTransformer
     */
    private StepTransformer $processor;

    /**
     * Creates a collection processor without executing its step.
     *
     * @param Transformer<mixed, mixed>|Validator<mixed>|Pipeline $step The behavior for each value.
     * @param CollectionKeys $keys The input key constraint.
     *
     * @throws AmbiguousProcessingStepException When the step implements multiple roles.
     */
    public function __construct(
        Transformer|Validator|Pipeline $step,
        private CollectionKeys $keys = CollectionKeys::Preserve,
    ) {
        $this->processor = new StepTransformer($step);
    }

    /**
     * {@inheritDoc}
     *
     * Items run in array order. Empty arrays succeed; failures expose no partial output.
     * Nested objects are not cloned, and reused steps must not mutate inputs.
     *
     * @throws Throwable When item execution fails, propagated unchanged without continuing.
     */
    #[Override]
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_array($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_ARRAY, 'Value must be an array.'));
        }
        if ($this->keys === CollectionKeys::RequireList && !array_is_list($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_LIST, 'Value must be a list.'));
        }
        $output = [];
        $violations = [];
        foreach ($value as $key => $entry) {
            $result = $this->processor->transform($entry);
            if ($result->isValid()) {
                $output[$key] = $result->value();
            } else {
                foreach ($result->violations() as $violation) {
                    $violations[] = $violation->withPrefix($key);
                }
            }
        }

        return $violations === []
            ? ProcessingResult::success($output)
            : ProcessingResult::failure(...$violations);
    }
}
