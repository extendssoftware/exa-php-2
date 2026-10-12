<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\String;

use ExtendsSoftware\ExaPHP\Processing\FailedProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SuccessfulProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use Override;

use function is_string;
use function trim;

/**
 * Removes PHP default trim characters from both ends of a string.
 *
 * @implements Transformer<mixed, string>
 */
final readonly class TrimString implements Transformer
{
    /**
     * The input is not a string.
     */
    public const string CODE_NOT_STRING = 'not_string';

    /**
     * {@inheritDoc}
     *
     * Non-string input produces a `not_string` violation without coercion.
     */
    #[Override]
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_string($value)) {
            return new FailedProcessingResult(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }

        return new SuccessfulProcessingResult(trim($value));
    }
}
