<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidProblemDetailsException;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;

use function in_array;
use function is_array;
use function is_float;
use function is_finite;
use function is_scalar;
use function is_string;

/**
 * Carries immutable RFC 9457 problem details and application extension data.
 */
final readonly class ProblemDetails
{
    /**
     * Application extension values detached from caller-owned array references.
     *
     * @var array<string, mixed>
     */
    public array $extensions;

    /**
     * Creates a problem with an explicit status and title.
     *
     * Extension values contain only null, scalars, and nested arrays up to 64 levels deep. Objects and resources are
     * rejected to keep the value immutable. Standard member names cannot be overridden by extensions.
     *
     * @param StatusCode $status The HTTP status to use in both the document and response.
     * @param string $title The short summary of the problem type.
     * @param Uri $type The problem type identifier, defaulting to generic HTTP status semantics.
     * @param string|null $detail A safe explanation specific to this occurrence, omitted when null.
     * @param Uri|null $instance The occurrence identifier, omitted when null.
     * @param array<string, mixed> $extensions Additional application-defined members.
     *
     * @throws InvalidProblemDetailsException When extension names or values are invalid.
     */
    public function __construct(
        public StatusCode $status,
        public string $title,
        public Uri $type = new Uri('about:blank'),
        public ?string $detail = null,
        public ?Uri $instance = null,
        array $extensions = [],
    ) {
        $normalized = [];
        foreach ($extensions as $name => $value) {
            if (!is_string($name) || $name === ''
                || in_array($name, ['type', 'title', 'status', 'detail', 'instance'], true)) {
                throw new InvalidProblemDetailsException('Extension names must be nonempty strings and not reserved.');
            }
            $normalized[$name] = $this->normalizeExtension($value, 0);
        }
        $this->extensions = $normalized;
    }

    /**
     * Returns the document members, omitting absent optional fields.
     *
     * @return array<string, mixed> The problem representation.
     */
    public function toArray(): array
    {
        $members = ['type' => $this->type->toString(), 'title' => $this->title, 'status' => $this->status->value];
        if ($this->detail !== null) {
            $members['detail'] = $this->detail;
        }
        if ($this->instance !== null) {
            $members['instance'] = $this->instance->toString();
        }

        return $members + $this->extensions;
    }

    /**
     * Validates and copies nested extension values with bounded recursion.
     *
     * @param mixed $value The extension value.
     * @param int $depth The current nesting depth.
     *
     * @return mixed The immutable-compatible value with array references detached.
     *
     * @throws InvalidProblemDetailsException When a value is unsupported or nesting exceeds the limit.
     */
    private function normalizeExtension(mixed $value, int $depth): mixed
    {
        if ($depth > 64) {
            throw new InvalidProblemDetailsException('Problem extension nesting must not exceed 64 levels.');
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $entry) {
                $result[$key] = $this->normalizeExtension($entry, $depth + 1);
            }

            return $result;
        }
        if (($value !== null && !is_scalar($value)) || (is_float($value) && !is_finite($value))) {
            throw new InvalidProblemDetailsException('Extensions require finite scalar, null, or array values.');
        }

        return $value;
    }
}
