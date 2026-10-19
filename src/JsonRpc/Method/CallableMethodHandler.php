<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\JsonRpc\Method;

use Closure;
use Override;
use SensitiveParameter;
use stdClass;

/**
 * Executes a JSON-RPC method through an application callable.
 */
final readonly class CallableMethodHandler implements MethodHandler
{
    /**
     * The method implementation.
     *
     * @var Closure(list<mixed>|stdClass): mixed
     */
    private Closure $handler;

    /**
     * Creates a method handler from a callable receiving the complete parameter structure.
     *
     * @param callable(list<mixed>|stdClass): mixed $handler The method implementation.
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler(...);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function handle(#[SensitiveParameter] array|stdClass $params): mixed
    {
        return ($this->handler)($params);
    }
}
