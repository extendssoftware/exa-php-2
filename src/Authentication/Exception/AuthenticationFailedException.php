<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authentication\Exception;

use ExtendsSoftware\ExaPHP\Authentication\AuthenticationException;
use RuntimeException;

/**
 * Indicates that an operational failure prevented credential verification.
 */
final class AuthenticationFailedException extends RuntimeException implements AuthenticationException
{
}
