<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Fixture;

use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Method;
use ExtendsSoftware\ExaPHP\Http\Request;
use ExtendsSoftware\ExaPHP\Http\Response;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\Http\Uri;
use TypeError;

/**
 * Records HTTP runner operations and optionally fails a configured stage.
 */
final class RunnerServices implements ServerRequestFactory, RequestHandler, ResponseEmitter
{
    /**
     * Operations in execution order.
     *
     * @var list<string>
     */
    public array $calls = [];

    /**
     * Creates the recorder.
     *
     * @param string $failureStage The operation that should fail, or an empty string.
     */
    public function __construct(private readonly string $failureStage = '')
    {
    }

    /**
     * Creates a HEAD request.
     *
     * @return Request The test request.
     *
     * @throws TypeError When creation is configured to fail.
     */
    public function create(): Request
    {
        $this->record('create');

        return new Request(Method::Head, new Uri('/'));
    }

    /**
     * Records dispatch and returns a response.
     *
     * @param Request $request The incoming request.
     *
     * @return Response The test response.
     *
     * @throws TypeError When handling is configured to fail.
     */
    public function handle(Request $request): Response
    {
        $this->record('handle');

        return new Response();
    }

    /**
     * Records emission and the originating method without producing output.
     *
     * @param Response $response The outgoing response.
     * @param Method $requestMethod The originating method.
     *
     * @return void
     *
     * @throws TypeError When emission is configured to fail.
     */
    public function emit(Response $response, Method $requestMethod): void
    {
        $this->record('emit:' . $requestMethod->value);
    }

    /**
     * Records a lifecycle step and raises any configured failure.
     *
     * @param string $stage The operation name.
     *
     * @return void
     *
     * @throws TypeError When this operation is configured to fail.
     */
    public function record(string $stage): void
    {
        $this->calls[] = $stage;
        if ($stage === $this->failureStage) {
            throw new TypeError($stage);
        }
    }
}
