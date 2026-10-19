<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Method;

use ExtendsSoftware\ExaPHP\JsonRpc\Method\Exception\MethodCallException;
use SensitiveParameter;
use stdClass;
use Throwable;

/**
 * Executes a registered JSON-RPC method for requests and notifications.
 */
interface MethodHandler
{
    /**
     * Validates method arguments and executes the operation.
     *
     * Parameters retain their positional array or named object structure. Omitted parameters are supplied as an empty
     * positional array. The result must support PHP JSON encoding; notification results are discarded.
     *
     * @param list<mixed>|stdClass $params The positional or named method arguments.
     *
     * @return mixed The JSON-encodable method result.
     *
     * @throws MethodCallException When execution fails with an error intended for the caller.
     * @throws Throwable When unexpected execution failures occur, translated to generic internal errors by the server.
     */
    public function handle(#[SensitiveParameter] array|stdClass $params): mixed;
}
