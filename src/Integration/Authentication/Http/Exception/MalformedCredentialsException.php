<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Authentication\Http\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use RuntimeException;

/**
 * Indicates malformed HTTP authentication credentials without retaining credential data.
 */
final class MalformedCredentialsException extends RuntimeException implements IntegrationException
{
}
