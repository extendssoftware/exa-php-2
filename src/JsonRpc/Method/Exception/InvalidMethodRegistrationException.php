<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception;

use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcException;
use InvalidArgumentException;

/**
 * Indicates that a registered method uses a reserved name or an invalid handler.
 */
final class InvalidMethodRegistrationException extends InvalidArgumentException implements JsonRpcException {}
