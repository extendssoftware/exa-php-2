<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authorization\Exception;

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationException;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;
use RuntimeException;

/**
 * Indicates that an authorization request was denied.
 */
final class AccessDeniedException extends RuntimeException implements AuthorizationException
{
    /**
     * Creates a denial retaining the request without embedding its data in the exception message.
     *
     * @param AuthorizationRequest $request The denied authorization request.
     */
    public function __construct(public readonly AuthorizationRequest $request)
    {
        parent::__construct('Access to the requested action was denied.');
    }
}
