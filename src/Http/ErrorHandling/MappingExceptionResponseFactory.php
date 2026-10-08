<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidExceptionProblemDetailsMapperException;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Override;
use Throwable;
use SensitiveParameter;

use function array_is_list;

/**
 * Renders the first mapped problem and delegates unmatched exceptions to a fallback.
 */
final readonly class MappingExceptionResponseFactory implements ExceptionResponseFactory
{
    /**
     * Creates an ordered exception mapping policy.
     *
     * @param list<ExceptionProblemDetailsMapper> $mappers The mappers in evaluation order.
     * @param ExceptionResponseFactory $fallback The policy for exceptions no mapper handles.
     * @param ProblemDetailsResponseFactory $problems The problem response encoder.
     *
     * @throws InvalidExceptionProblemDetailsMapperException When mappers are not a list of mapper instances.
     */
    public function __construct(
        private array $mappers,
        private ExceptionResponseFactory $fallback,
        private ProblemDetailsResponseFactory $problems = new ProblemDetailsResponseFactory(),
    ) {
        if (!array_is_list($mappers)) {
            throw new InvalidExceptionProblemDetailsMapperException('Problem Details mappers must be a list.');
        }
        foreach ($mappers as $mapper) {
            if (!$mapper instanceof ExceptionProblemDetailsMapper) {
                throw new InvalidExceptionProblemDetailsMapperException(
                    'Each mapper must implement ExceptionProblemDetailsMapper.',
                );
            }
        }
    }

    /**
     * Passes the original exception and request to each mapper until one returns a problem.
     *
     * Mapper and encoding failures propagate without trying later mappers or the fallback.
     *
     * @param Throwable $exception The original execution failure.
     * @param Request $request The request at the exception boundary.
     *
     * @return Response The mapped problem response or the fallback response.
     *
     * @throws Throwable When mapping, encoding, or fallback creation fails, propagated unchanged.
     */
    #[Override]
    public function create(Throwable $exception, #[SensitiveParameter] Request $request): Response
    {
        foreach ($this->mappers as $mapper) {
            $problem = $mapper->map($exception, $request);
            if ($problem !== null) {
                return $this->problems->create($problem, protocolVersion: $request->protocolVersion);
            }
        }

        return $this->fallback->create($exception, $request);
    }
}
