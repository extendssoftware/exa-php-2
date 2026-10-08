<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Authentication\Exception;

use ExtendsSoftware\ExaPHP\Authentication\AuthenticationException;
use RuntimeException;

/**
 * Indicates that authentication credentials were rejected.
 */
final class InvalidCredentialsException extends RuntimeException implements AuthenticationException
{
}
