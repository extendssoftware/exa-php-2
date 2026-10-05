<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http;

use ExtendsSoftware\ExaPHP\Http\Exception\RequestAttributeNotFoundException;
use ExtendsSoftware\ExaPHP\Http\Exception\InvalidRequestAttributesException;

use function is_object;
use function sprintf;

/**
 * Carries application-defined metadata indexed by exact concrete class.
 *
 * The collection is immutable; stored objects retain their identity and should themselves be immutable.
 */
final readonly class RequestAttributes
{
    /**
     * Metadata indexed by concrete class name.
     *
     * @var array<class-string, object>
     */
    private array $metadata;

    /**
     * Creates an attribute collection from metadata objects, ignoring input array keys.
     *
     * @param array<array-key, object> $metadata The request metadata.
     *
     * @throws InvalidRequestAttributesException When an entry is not an object or a concrete class occurs twice.
     */
    public function __construct(array $metadata = [])
    {
        $entries = [];
        foreach ($metadata as $entry) {
            if (!is_object($entry)) {
                throw new InvalidRequestAttributesException('Request attribute entries must be objects.');
            }
            $class = $entry::class;
            if (isset($entries[$class])) {
                throw new InvalidRequestAttributesException(
                    sprintf('Request attribute "%s" occurs more than once.', $class),
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
     * @throws RequestAttributeNotFoundException When the concrete class name has no metadata.
     */
    public function get(string $class): object
    {
        return $this->metadata[$class]
            ?? throw new RequestAttributeNotFoundException(sprintf('Request attributes "%s" is not present.', $class));
    }

    /**
     * Returns a attribute collection with metadata added or replaced by its concrete class.
     *
     * @param object $metadata The metadata to add or replace.
     *
     * @return self The new attribute collection, leaving this attribute collection unchanged.
     */
    public function with(object $metadata): self
    {
        $entries = $this->metadata;
        $entries[$metadata::class] = $metadata;

        return new self($entries);
    }

    /**
     * Returns a collection without metadata of the given concrete class.
     *
     * @param class-string $class The metadata class to remove.
     *
     * @return self The new collection, leaving this collection unchanged.
     */
    public function without(string $class): self
    {
        $entries = $this->metadata;
        unset($entries[$class]);

        return new self($entries);
    }
}
