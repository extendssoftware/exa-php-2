<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Identity;

use ExtendsSoftware\ExaPHP\Identity\Exception\InvalidActorException;

/**
 * Identifies the principal performing an operation.
 *
 * The kind and identifier together identify the principal. An actor carries no authentication proof or permissions.
 */
final readonly class Actor
{
    /**
     * Creates an actor using an application-defined identifier and kind.
     *
     * Values are case-sensitive and preserved without normalization. Identifiers are scoped to their kind.
     *
     * @param non-empty-string $id The principal identifier within its kind.
     * @param non-empty-string $kind The application-defined principal kind.
     *
     * @throws InvalidActorException When the identifier or kind is empty.
     */
    public function __construct(public string $id, public string $kind)
    {
        if ($id === '') {
            throw new InvalidActorException('Actor identifiers must not be empty.');
        }
        if ($kind === '') {
            throw new InvalidActorException('Actor kinds must not be empty.');
        }
    }
}
