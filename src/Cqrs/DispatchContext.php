<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs;

use ExtendsSoftware\ExaPHP\Cqrs\Exception\DispatchMetadataNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidDispatchMetadataException;

use function is_object;
use function sprintf;

/**
 * Carries application-defined metadata indexed by exact concrete class.
 *
 * The collection is immutable; stored objects retain their identity and should themselves be immutable.
 */
final readonly class DispatchContext
{
    /**
     * Metadata indexed by concrete class name.
     *
     * @var array<class-string, object>
     */
    private array $metadata;

    /**
     * Creates a context from metadata objects, ignoring input array keys.
     *
     * @param array<array-key, object> $metadata The execution metadata.
     *
     * @throws InvalidDispatchMetadataException When an entry is not an object or a concrete class occurs twice.
     */
    public function __construct(array $metadata = [])
    {
        $entries = [];
        foreach ($metadata as $entry) {
            if (!is_object($entry)) {
                throw new InvalidDispatchMetadataException('Dispatch metadata entries must be objects.');
            }
            $class = $entry::class;
            if (isset($entries[$class])) {
                throw new InvalidDispatchMetadataException(
                    sprintf('Dispatch metadata "%s" occurs more than once.', $class),
                );
            }
            $entries[$class] = $entry;
        }
        $this->metadata = $entries;
    }

    /**
     * Checks for metadata by its exact concrete class name.
     *
     * @param class-string $class The metadata class.
     *
     * @return bool Whether metadata is present.
     */
    public function has(string $class): bool
    {
        return isset($this->metadata[$class]);
    }

    /**
     * Returns metadata by its exact concrete class name.
     *
     * @template T of object
     *
     * @param class-string<T> $class The metadata class.
     *
     * @return T The metadata object.
     *
     * @throws DispatchMetadataNotFoundException When the concrete class name has no metadata.
     */
    public function get(string $class): object
    {
        return $this->metadata[$class]
            ?? throw new DispatchMetadataNotFoundException(sprintf('Dispatch metadata "%s" is not present.', $class));
    }

    /**
     * Returns a context with metadata added or replaced by its concrete class.
     *
     * @param object $metadata The metadata to add or replace.
     *
     * @return self The new context, leaving this context unchanged.
     */
    public function with(object $metadata): self
    {
        $entries = $this->metadata;
        $entries[$metadata::class] = $metadata;

        return new self($entries);
    }
}
