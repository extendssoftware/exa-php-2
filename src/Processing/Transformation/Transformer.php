<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation;

use ExtendsSoftware\ExaPHP\Processing\ProcessingException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;

/**
 * Produces a value from input without modifying the input or its nested objects.
 *
 * @template TInput
 * @template TOutput
 */
interface Transformer
{
    /**
     * Transforms input or reports violations when the input cannot be transformed.
     *
     * Ordinary input failures must be returned as violations rather than thrown as exceptions.
     *
     * @param TInput $value The input value.
     *
     * @return ProcessingResult<TOutput> The transformed value or input violations.
     *
     * @throws ProcessingException When configuration or execution prevents transformation.
     */
    public function transform(mixed $value): ProcessingResult;
}
