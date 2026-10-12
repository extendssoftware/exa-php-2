<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\ErrorHandling;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidExceptionProblemDetailsMapperException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use Override;
use SensitiveParameter;
use Throwable;

use function array_is_list;

/**
 * Renders the first mapped problem and delegates unmatched exceptions to a fallback.
 */
final readonly class MappingExceptionResponseFactory implements ExceptionResponseFactory
{
    /**
     * Validated mappers in evaluation order.
     *
     * @var list<ExceptionProblemDetailsMapper>
     */
    private array $mappers;

    /**
     * Creates an ordered exception mapping policy.
     *
     * @param array<array-key, mixed> $mappers The mappers to validate, in evaluation order.
     * @param ExceptionResponseFactory $fallback The policy for exceptions no mapper handles.
     * @param ProblemDetailsResponseFactory $problems The problem response encoder.
     *
     * @throws InvalidExceptionProblemDetailsMapperException When mappers are not a list of mapper instances.
     */
    public function __construct(
        array $mappers,
        private ExceptionResponseFactory $fallback,
        private ProblemDetailsResponseFactory $problems = new ProblemDetailsResponseFactory(),
    ) {
        if (!array_is_list($mappers)) {
            throw new InvalidExceptionProblemDetailsMapperException('Problem Details mappers must be a list.');
        }
        $validatedMappers = [];
        foreach ($mappers as $mapper) {
            if (!$mapper instanceof ExceptionProblemDetailsMapper) {
                throw new InvalidExceptionProblemDetailsMapperException(
                    'Each mapper must implement ExceptionProblemDetailsMapper.',
                );
            }
            $validatedMappers[] = $mapper;
        }
        $this->mappers = $validatedMappers;
    }

    /**
     * {@inheritDoc}
     *
     * Mapper and encoding failures propagate without trying later mappers or the fallback.
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
