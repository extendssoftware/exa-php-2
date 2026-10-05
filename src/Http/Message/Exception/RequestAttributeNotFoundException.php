<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use OutOfBoundsException;

/**
 * Indicates absent typed request metadata.
 */
final class RequestAttributeNotFoundException extends OutOfBoundsException implements HttpException
{
}
