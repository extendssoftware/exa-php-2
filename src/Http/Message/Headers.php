<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\Exception\InvalidHeaderException;

use function array_is_list;
use function array_values;
use function is_array;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Stores immutable case-insensitive headers with separate ordered field values.
 */
final readonly class Headers
{
    /**
     * Header entries indexed by lowercase name, preserving the first supplied spelling.
     *
     * @var array<array-key, array{name: string, values: non-empty-list<string>}>
     */
    private array $entries;

    /**
     * Creates headers, merging repeated names case-insensitively without joining their values.
     *
     * @param array<array-key, string|non-empty-list<string>> $headers Names mapped to one or more field values.
     *
     * @throws InvalidHeaderException When a name or value is invalid or a value list is empty or not a list.
     */
    public function __construct(array $headers = [])
    {
        $entries = [];
        foreach ($headers as $name => $values) {
            $name = (string) $name;
            $key = self::key($name);
            $values = is_string($values) ? [$values] : $values;
            if (!is_array($values) || !array_is_list($values) || $values === []) {
                throw new InvalidHeaderException('Header values must be a string or a non-empty list of strings.');
            }
            foreach ($values as $value) {
                if (!is_string($value) || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
                    throw new InvalidHeaderException('Header values must be strings without prohibited control bytes.');
                }
                $entries[$key] ??= ['name' => $name, 'values' => []];
                $entries[$key]['values'][] = trim($value, " \t");
            }
        }
        $this->entries = $entries;
    }

    /**
     * Checks whether a field name is present, ignoring case.
     *
     * @param string $name The header name.
     *
     * @return bool Whether at least one field value exists.
     *
     * @throws InvalidHeaderException When the name is invalid.
     */
    public function has(string $name): bool
    {
        return isset($this->entries[self::key($name)]);
    }

    /**
     * Returns separate field values without combining comma-sensitive headers.
     *
     * @param string $name The header name.
     *
     * @return list<string> Values in order, or an empty list when absent.
     *
     * @throws InvalidHeaderException When the name is invalid.
     */
    public function get(string $name): array
    {
        return $this->entries[self::key($name)]['values'] ?? [];
    }

    /**
     * Returns all fields using their retained spelling and insertion order.
     *
     * @return array<array-key, non-empty-list<string>> Header names mapped to separate values.
     */
    public function all(): array
    {
        $headers = [];
        foreach ($this->entries as $entry) {
            $headers[$entry['name']] = $entry['values'];
        }

        return $headers;
    }

    /**
     * Returns a copy with the named header replaced, using the supplied spelling.
     *
     * @param string $name The header name.
     * @param string $value The first field value, which may be empty.
     * @param string ...$values Additional separate field values.
     *
     * @return self The updated collection.
     *
     * @throws InvalidHeaderException When a name or value is invalid.
     */
    public function with(string $name, string $value, string ...$values): self
    {
        $headers = $this->without($name)->all();
        $headers[$name] = [$value, ...array_values($values)];

        return new self($headers);
    }

    /**
     * Returns a copy with additional field values appended to the named header.
     *
     * @param string $name The header name.
     * @param string $value The first additional value.
     * @param string ...$values Further values.
     *
     * @return self The updated collection retaining existing spelling when present.
     *
     * @throws InvalidHeaderException When a name or value is invalid.
     */
    public function withAdded(string $name, string $value, string ...$values): self
    {
        $key = self::key($name);
        $headers = $this->all();
        $name = $this->entries[$key]['name'] ?? $name;
        $headers[$name] = [...($headers[$name] ?? []), $value, ...array_values($values)];

        return new self($headers);
    }

    /**
     * Returns a copy without the named header.
     *
     * @param string $name The header name.
     *
     * @return self The updated collection.
     *
     * @throws InvalidHeaderException When the name is invalid.
     */
    public function without(string $name): self
    {
        $key = self::key($name);
        $headers = $this->all();
        if (isset($this->entries[$key])) {
            unset($headers[$this->entries[$key]['name']]);
        }

        return new self($headers);
    }

    /**
     * Validates a field name and returns its case-insensitive key.
     *
     * @param string $name The field name.
     *
     * @return string The lowercase key.
     *
     * @throws InvalidHeaderException When the name is not an HTTP token.
     */
    private static function key(string $name): string
    {
        if (preg_match('/\A[!#$%&\'*+.^_`|~0-9A-Za-z-]+\z/', $name) !== 1) {
            throw new InvalidHeaderException('Header names must be non-empty HTTP tokens.');
        }

        return strtolower($name);
    }
}
