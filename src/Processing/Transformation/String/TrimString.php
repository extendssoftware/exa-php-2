<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\String;

use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Violation;

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
     * Trims a string without coercing other input types.
     *
     * @param mixed $value The input value.
     *
     * @return ProcessingResult<string> The trimmed string, or a not_string violation.
     */
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_string($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }

        return ProcessingResult::success(trim($value));
    }
}
