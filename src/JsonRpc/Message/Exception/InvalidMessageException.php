<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Message\Exception;

use ExtendsSoftware\ExaPHP\JsonRpc\JsonRpcException;
use InvalidArgumentException;

/**
 * Indicates that message fields violate JSON-RPC envelope invariants.
 */
final class InvalidMessageException extends InvalidArgumentException implements JsonRpcException {}
