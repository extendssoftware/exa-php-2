<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Messaging\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates an invalid mapping from subscriber identifiers to services.
 */
final class InvalidSubscriberMappingException extends InvalidArgumentException implements IntegrationException
{
}
