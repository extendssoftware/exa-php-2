<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidViolationException;

use function array_is_list;
use function array_keys;
use function is_int;
use function is_string;

/**
 * Describes an input failure without changing the input.
 */
final readonly class Violation
{
    /**
     * Creates a violation with a stable code and optional location and parameters.
     *
     * Context objects in parameters are retained without cloning or freezing them.
     *
     * @param non-empty-string $code The machine-readable failure code.
     * @param string $message The diagnostic message.
     * @param list<string|int> $path Property names or collection keys; an empty list identifies the root.
     * @param array<string, mixed> $parameters Named parameters describing the failure.
     *
     * @throws InvalidViolationException When the code, path, or parameter keys are invalid.
     */
    public function __construct(
        private string $code,
        private string $message,
        private array $path = [],
        private array $parameters = [],
    ) {
        if ($code === '') {
            throw new InvalidViolationException('Violation code must not be empty.');
        }
        if (!array_is_list($path)) {
            throw new InvalidViolationException('Violation path must be a list.');
        }
        foreach ($path as $segment) {
            if (!is_string($segment) && !is_int($segment)) {
                throw new InvalidViolationException('Violation path segments must be strings or integers.');
            }
        }
        foreach (array_keys($parameters) as $key) {
            if (!is_string($key)) {
                throw new InvalidViolationException('Violation parameter keys must be strings.');
            }
        }
    }

    /**
     * Returns a new violation with a field or collection key prepended to its path.
     *
     * @param string|int $segment The parent field name or collection key.
     *
     * @return self The prefixed violation with the same code, message, and parameters.
     */
    public function withPrefix(string|int $segment): self
    {
        return new self($this->code, $this->message, [$segment, ...$this->path], $this->parameters);
    }

    /**
     * Returns the location of the invalid value relative to the input root.
     *
     * @return list<string|int> Property names or collection keys; an empty list identifies the root.
     */
    public function path(): array
    {
        return $this->path;
    }

    /**
     * Returns a stable machine-readable identifier for the failure.
     *
     * @return non-empty-string The violation code.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Returns a human-readable explanation of the failure.
     *
     * @return string The diagnostic message.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Returns parameters describing the failure.
     *
     * @return array<string, mixed> Named parameters for interpreting or presenting the violation.
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
