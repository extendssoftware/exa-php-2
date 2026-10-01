<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query;

use ExtendsSoftware\ExaPHP\Cqrs\Exception\DuplicateQueryHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidQueryRegistrationException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\QueryHandlerNotFoundException;
use ReflectionClass;
use Throwable;

use function array_key_exists;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Answers queries immediately using handlers registered for their exact class.
 *
 * Registrations are fixed after construction and handler instances are reused. Class aliases and differently cased
 * names are normalized. Registration validates the runtime contracts, not handler PHPDoc generic bindings.
 */
final readonly class SynchronousQueryBus implements QueryBus
{
    /**
     * Handler instances indexed by canonical query class.
     *
     * @var array<class-string<Query>, QueryHandler>
     */
    private array $handlers;

    /**
     * Creates a bus with validated query handler registrations.
     *
     * @param array<class-string<Query>, QueryHandler> $handlers Handlers indexed by concrete query class.
     *
     * @throws InvalidQueryRegistrationException When a key is not a concrete Query class or a handler is invalid.
     * @throws DuplicateQueryHandlerException When multiple keys identify the same canonical query class.
     */
    public function __construct(array $handlers)
    {
        $registrations = [];
        foreach ($handlers as $queryClass => $handler) {
            if (!is_string($queryClass) || $queryClass === '') {
                throw new InvalidQueryRegistrationException('Query registration keys must be non-empty class names.');
            }

            try {
                $class = new ReflectionClass($queryClass);
            } catch (Throwable $exception) {
                throw new InvalidQueryRegistrationException(
                    sprintf('Could not inspect registered query class "%s".', $queryClass),
                    0,
                    $exception,
                );
            }

            if ($class->isInterface() || $class->isAbstract() || !$class->implementsInterface(Query::class)) {
                throw new InvalidQueryRegistrationException(
                    sprintf('Registered class "%s" must be a concrete Query.', $queryClass),
                );
            }

            if (!$handler instanceof QueryHandler) {
                throw new InvalidQueryRegistrationException(
                    sprintf(
                        'Handler for query "%s" must implement QueryHandler, %s given.',
                        $queryClass,
                        get_debug_type($handler),
                    ),
                );
            }

            $name = $class->getName();
            if (array_key_exists($name, $registrations)) {
                throw new DuplicateQueryHandlerException(
                    sprintf('A handler is already registered for query "%s".', $name),
                );
            }

            $registrations[$name] = $handler;
        }

        $this->handlers = $registrations;
    }

    /**
     * Invokes the handler for the query's exact class and waits for it to complete.
     *
     * Parent classes and interfaces are not considered. The original query object is passed to the handler.
     * Handler exceptions and engine errors propagate unchanged.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to answer.
     *
     * @return TResult The unchanged handler result.
     *
     * @throws QueryHandlerNotFoundException When the exact query class has no registered handler.
     * @throws Throwable When the handler fails, propagated unchanged.
     */
    public function ask(Query $query): mixed
    {
        $class = $query::class;
        if (!isset($this->handlers[$class])) {
            throw new QueryHandlerNotFoundException(sprintf('No handler is registered for query "%s".', $class));
        }

        return $this->handlers[$class]->handle($query);
    }
}
