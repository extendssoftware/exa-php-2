<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Outbox\Processing\Exception;

use ExtendsSoftware\ExaPHP\Outbox\OutboxException;
use InvalidArgumentException;

/**
 * Indicates invalid outbox claim data or an invalid lease duration.
 */
final class InvalidClaimException extends InvalidArgumentException implements OutboxException
{
}
