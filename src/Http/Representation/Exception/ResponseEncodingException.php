<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Representation\Exception;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use RuntimeException;

/**
 * Indicates that response data could not be encoded.
 */
final class ResponseEncodingException extends RuntimeException implements HttpException
{
}
