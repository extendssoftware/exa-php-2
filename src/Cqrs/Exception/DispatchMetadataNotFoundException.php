<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Exception;

use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use RuntimeException;

/**
 * Indicates that requested dispatch metadata is absent.
 */
final class DispatchMetadataNotFoundException extends RuntimeException implements CqrsException
{
}
