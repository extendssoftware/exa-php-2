<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Configuration;

use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\ConfigurationNotFoundException;
use ExtendsSoftware\ExaPHP\Application\Configuration\Exception\InvalidConfigurationPathException;

use function array_key_exists;
use function explode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Provides read-only access to configuration values through dot-separated paths.
 *
 * Paths traverse arrays only. Null values are present values. Stored objects retain their identity and mutability.
 */
final readonly class Configuration
{
    /**
     * Creates configuration from its values.
     *
     * @param array<array-key, mixed> $values The configuration values.
     */
    public function __construct(private array $values)
    {
    }

    /**
     * Returns the value at a dot-separated path.
     *
     * @param string $path The configuration path with non-empty segments.
     *
     * @return mixed The configured value, including null.
     *
     * @throws InvalidConfigurationPathException When the path contains an empty segment.
     * @throws ConfigurationNotFoundException When a segment is missing or traverses a non-array value.
     */
    public function get(string $path): mixed
    {
        $value = $this->values;
        foreach ($this->segments($path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new ConfigurationNotFoundException(sprintf('Configuration path "%s" does not exist.', $path));
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Checks whether a dot-separated path exists, including when its value is null.
     *
     * @param string $path The configuration path with non-empty segments.
     *
     * @return bool Whether every path segment exists and intermediate values are arrays.
     *
     * @throws InvalidConfigurationPathException When the path contains an empty segment.
     */
    public function has(string $path): bool
    {
        $value = $this->values;
        foreach ($this->segments($path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Splits and validates a configuration path.
     *
     * @param string $path The configuration path.
     *
     * @return non-empty-list<non-empty-string> The path segments.
     *
     * @throws InvalidConfigurationPathException When the path contains an empty segment.
     */
    private function segments(string $path): array
    {
        $segments = explode('.', $path);
        if (in_array('', $segments, true)) {
            throw new InvalidConfigurationPathException(
                sprintf('Configuration path "%s" must contain only non-empty segments.', $path),
            );
        }

        return $segments;
    }
}
