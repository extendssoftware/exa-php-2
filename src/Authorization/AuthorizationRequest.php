<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authorization;

use ExtendsSoftware\ExaPHP\Authorization\Exception\InvalidAuthorizationRequestException;
use ExtendsSoftware\ExaPHP\Identity\Actor;

/**
 * Describes an actor's requested action and optional target resource.
 *
 * The request preserves the supplied resource reference; it does not clone or freeze the resource's state.
 */
final readonly class AuthorizationRequest
{
    /**
     * Creates a request using a case-sensitive, application-defined action.
     *
     * @param Actor $actor The principal requesting access.
     * @param non-empty-string $action The requested action, preserved without normalization.
     * @param object|null $resource The target resource, or null for an action without a particular resource.
     *
     * @throws InvalidAuthorizationRequestException When the action is empty.
     */
    public function __construct(public Actor $actor, public string $action, public ?object $resource = null)
    {
        if ($action === '') {
            throw new InvalidAuthorizationRequestException('Authorization actions must not be empty.');
        }
    }
}
