<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc;

use Throwable;

/**
 * Identifies JSON-RPC message validation, registration, and method execution failures.
 */
interface JsonRpcException extends Throwable {}
