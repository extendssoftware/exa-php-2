<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use InvalidArgumentException;

/**
 * Indicates invalid or duplicate dispatch metadata.
 */
final class InvalidDispatchMetadataException extends InvalidArgumentException implements CqrsException
{
}
