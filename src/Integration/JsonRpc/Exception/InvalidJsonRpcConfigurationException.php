<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\JsonRpc\Exception;

use ExtendsSoftware\ExaPHP\Integration\IntegrationException;
use InvalidArgumentException;

/**
 * Indicates malformed JSON-RPC integration configuration or incompatible collaborator services.
 */
final class InvalidJsonRpcConfigurationException extends InvalidArgumentException implements IntegrationException {}
