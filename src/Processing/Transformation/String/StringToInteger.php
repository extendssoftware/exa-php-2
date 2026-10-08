<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\String;

use Override;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Violation;

use function is_string;
use function ltrim;
use function preg_match;
use function strcmp;
use function strlen;
use function substr;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Parses signed decimal strings within the platform integer range without truncation.
 *
 * @implements Transformer<mixed, int>
 */
final readonly class StringToInteger implements Transformer
{
    /**
     * The input is not a string.
     */
    public const string CODE_NOT_STRING = 'not_string';

    /**
     * The string does not represent a signed decimal integer.
     */
    public const string CODE_INVALID_INTEGER_FORMAT = 'invalid_integer_format';

    /**
     * The decimal value exceeds the platform integer range.
     */
    public const string CODE_INTEGER_OVERFLOW = 'integer_overflow';

    /**
     * {@inheritDoc}
     *
     * Accepts ASCII digits with an optional leading sign and leading zeros, but no whitespace.
     */
    #[Override]
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_string($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }
        if (preg_match('/\A[+-]?[0-9]+\z/', $value) !== 1) {
            return ProcessingResult::failure(
                new Violation(
                    self::CODE_INVALID_INTEGER_FORMAT,
                    'Value must be a decimal integer string.',
                ),
            );
        }

        $negative = $value[0] === '-';
        $digits = ltrim(ltrim($value, '+-'), '0');
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return ProcessingResult::failure(
                new Violation(
                    self::CODE_INTEGER_OVERFLOW,
                    'Value exceeds the platform integer range.',
                    parameters: ['minimum' => PHP_INT_MIN, 'maximum' => PHP_INT_MAX],
                ),
            );
        }

        return ProcessingResult::success((int) ($negative ? '-' . ($digits ?: '0') : ($digits ?: '0')));
    }
}
