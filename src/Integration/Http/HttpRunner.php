<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Application;
use ExtendsSoftware\ExaPHP\Http\Handler\RequestHandler;
use ExtendsSoftware\ExaPHP\Http\Server\ResponseEmitter;
use ExtendsSoftware\ExaPHP\Http\Server\ServerRequestFactory;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\HttpRunException;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use Throwable;

/**
 * Runs one HTTP request through an explicitly configured application and shuts it down.
 */
final readonly class HttpRunner
{
    /**
     * Bootstraps the application, creates and handles one request, emits its response, and shuts down.
     *
     * Modules must already be registered. Services are validated before request creation. After successful bootstrap,
     * shutdown is attempted even after execution errors. Bootstrap failure cleanup belongs to Application. The runner
     * neither emits fallback responses nor exits the process; partially emitted responses cannot be retracted.
     *
     * @param Application $application The configured application, which is stopped after this run.
     *
     * @return void
     *
     * @throws InvalidHttpConfigurationException When a required HTTP service has an incompatible type.
     * @throws HttpRunException When execution and shutdown both fail, preserving both failures.
     * @throws Throwable When bootstrap, HTTP execution, or shutdown alone fails, propagated unchanged.
     */
    public function run(Application $application): void
    {
        $services = $application->bootstrap();
        $failure = null;
        try {
            $factory = $services->get(ServerRequestFactory::class);
            $handler = $services->get(RequestHandler::class);
            $emitter = $services->get(ResponseEmitter::class);
            if (!$factory instanceof ServerRequestFactory || !$handler instanceof RequestHandler
                || !$emitter instanceof ResponseEmitter) {
                throw new InvalidHttpConfigurationException(
                    'HTTP runner services must implement their registered contracts.',
                );
            }
            $request = $factory->create();
            $response = $handler->handle($request);
            $emitter->emit($response, $request->method);
        } catch (Throwable $exception) {
            // Engine errors also require application cleanup before propagation.
            $failure = $exception;
        }
        try {
            $application->shutdown();
        } catch (Throwable $exception) {
            if ($failure !== null) {
                throw new HttpRunException($failure, $exception);
            }
            throw $exception;
        }
        if ($failure !== null) {
            throw $failure;
        }
    }
}
