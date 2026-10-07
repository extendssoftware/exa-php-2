<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authorization;

use ExtendsSoftware\ExaPHP\Authorization\Exception\AccessDeniedException;
use Throwable;

/**
 * Enforces authorization by throwing when an authorizer denies access.
 */
final readonly class AuthorizationGuard
{
    /**
     * Creates a guard using the application's authorization rules.
     *
     * @param Authorizer $authorizer The evaluator deciding whether access is granted.
     */
    public function __construct(private Authorizer $authorizer)
    {
    }

    /**
     * Returns normally only when the authorizer grants access.
     *
     * Evaluation failures propagate unchanged; only a denied decision becomes AccessDeniedException.
     *
     * @param AuthorizationRequest $request The request to evaluate and enforce.
     *
     * @return void
     *
     * @throws AccessDeniedException When the authorizer denies access.
     * @throws Throwable When authorization evaluation fails.
     */
    public function assertGranted(AuthorizationRequest $request): void
    {
        if (!$this->authorizer->isGranted($request)) {
            throw new AccessDeniedException($request);
        }
    }
}
