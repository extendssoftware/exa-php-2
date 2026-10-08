<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authentication\Exception;

use ExtendsSoftware\ExaPHP\Authentication\AuthenticationException;
use InvalidArgumentException;

/**
 * Indicates that an authenticator does not support the supplied credential type.
 */
final class UnsupportedCredentialsException extends InvalidArgumentException implements AuthenticationException
{
}
