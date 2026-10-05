<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Validation\String;

use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\Validator;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use ErrorException;
use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidPatternException;
use ExtendsSoftware\ExaPHP\Processing\Exception\PatternExecutionException;

use function is_string;
use function preg_match;
use function preg_last_error;
use function preg_last_error_msg;
use function set_error_handler;
use function restore_error_handler;

use const E_WARNING;
use const PREG_BAD_UTF8_ERROR;
use const PREG_INTERNAL_ERROR;

/**
 * Requires a string to match a delimited PCRE pattern with its supplied modifiers.
 *
 * @implements Validator<mixed>
 */
final readonly class MatchesPattern implements Validator
{
    /**
     * The input is not a string.
     */
    public const string CODE_NOT_STRING = 'not_string';

    /**
     * The string does not match the pattern.
     */
    public const string CODE_PATTERN_MISMATCH = 'pattern_mismatch';

    /**
     * The input is invalid UTF-8 for a Unicode pattern.
     */
    public const string CODE_INVALID_UTF8 = 'invalid_utf8';

    /**
     * Creates a validator and checks that the pattern compiles.
     *
     * @param string $pattern The complete PCRE pattern, including delimiters and optional modifiers.
     *
     * @throws InvalidPatternException When the pattern cannot compile.
     */
    public function __construct(private string $pattern)
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $result = preg_match($pattern, '');
            if ($result === false && preg_last_error() === PREG_INTERNAL_ERROR) {
                throw new InvalidPatternException('Pattern compilation failed: ' . preg_last_error_msg());
            }
        } catch (ErrorException $exception) {
            throw new InvalidPatternException('The validation pattern could not be compiled.', 0, $exception);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Matches without adding anchors or modifying the string.
     *
     * @param mixed $value The input value.
     *
     * @return ValidationResult The type, encoding, or mismatch violation, or a valid result.
     *
     * @throws PatternExecutionException When matching cannot complete, including regex resource limits.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return new ValidationResult(new Violation(self::CODE_NOT_STRING, 'Value must be a string.'));
        }
        $match = preg_match($this->pattern, $value);
        if ($match === false) {
            if (preg_last_error() === PREG_BAD_UTF8_ERROR) {
                return new ValidationResult(new Violation(self::CODE_INVALID_UTF8, 'Value must be valid UTF-8.'));
            }
            throw new PatternExecutionException('Pattern matching failed: ' . preg_last_error_msg());
        }

        return $match === 1
            ? new ValidationResult()
            : new ValidationResult(new Violation(self::CODE_PATTERN_MISMATCH, 'Value does not match the pattern.'));
    }
}
