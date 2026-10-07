<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authorization\Exception;

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationException;
use InvalidArgumentException;

/**
 * Indicates that an authorization request contains an empty action.
 */
final class InvalidAuthorizationRequestException extends InvalidArgumentException implements AuthorizationException
{
}
