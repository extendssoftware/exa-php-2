<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authorization;

use Throwable;

/**
 * Evaluates whether an actor may perform an action on an optional resource.
 */
interface Authorizer
{
    /**
     * Determines whether the requested access is granted without modifying the resource.
     *
     * Implementations must return false for denied or unsupported requests. Evaluation failures must propagate as
     * exceptions rather than grant access. The caller supplies the actor; this operation does not authenticate it.
     *
     * @param AuthorizationRequest $request The actor, action, and optional resource to evaluate.
     *
     * @return bool Whether access is granted.
     *
     * @throws Throwable When authorization evaluation fails.
     */
    public function isGranted(AuthorizationRequest $request): bool;
}
