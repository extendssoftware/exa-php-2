<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Server\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that a response cannot be emitted successfully.
 */
final class ResponseEmissionException extends RuntimeException implements HttpException
{
}
