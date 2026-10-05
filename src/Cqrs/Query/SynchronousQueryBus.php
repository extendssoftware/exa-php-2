<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Query;

use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\ClosureQueryExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\QueryExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Middleware\QueryMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Exception\InvalidQueryMiddlewareException;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Exception\QueryHandlerNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Exception\DuplicateQueryHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Query\Exception\InvalidQueryRegistrationException;
use ReflectionClass;
use Throwable;

use function array_is_list;
use function array_key_exists;
use function array_reverse;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Answers queries immediately to handlers registered for their exact class.
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
     * Fixed execution chain, reused without retaining per-dispatch context.
     */
    private QueryExecution $execution;

    /**
     * Creates a bus with validated query handler registrations.
     *
     * @param array<class-string<Query>, QueryHandler> $handlers Handlers indexed by concrete query class.
     * @param list<QueryMiddleware> $middleware Middleware in execution order, first entry outermost.
     *
     * @throws InvalidQueryRegistrationException When a key is not a concrete Query class or a handler is invalid.
     * @throws DuplicateQueryHandlerException When multiple keys identify the same canonical query class.
     * @throws InvalidQueryMiddlewareException When middleware is not a list of QueryMiddleware instances.
     */
    public function __construct(array $handlers, array $middleware = [])
    {
        $registrations = [];
        foreach ($handlers as $queryClass => $handler) {
            if (!is_string($queryClass) || $queryClass === '') {
                throw new InvalidQueryRegistrationException(
                    'Query registration keys must be non-empty class names.',
                );
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
        if (!array_is_list($middleware)) {
            throw new InvalidQueryMiddlewareException('Query middleware must be a list.');
        }
        foreach ($middleware as $entry) {
            if (!$entry instanceof QueryMiddleware) {
                throw new InvalidQueryMiddlewareException(
                    'Each query middleware must implement QueryMiddleware.',
                );
            }
        }

        $execution = new ClosureQueryExecution(function (Query $query, DispatchContext $context): mixed {
            $class = $query::class;
            if (!isset($this->handlers[$class])) {
                throw new QueryHandlerNotFoundException(
                    sprintf('No handler is registered for query "%s".', $class),
                );
            }
            return $this->handlers[$class]->handle($query);
        });
        foreach (array_reverse($middleware) as $entry) {
            $next = $execution;
            $execution = new ClosureQueryExecution(
                static function (Query $query, DispatchContext $context) use ($entry, $next): mixed {
                    return $entry->process($query, $context, $next);
                },
            );
        }
        $this->execution = $execution;
    }

    /**
     * Executes middleware followed by the handler for the query's exact class.
     *
     * Middleware may short-circuit or forward a replacement query and context. Handler lookup occurs only at the
     * end of the chain. Each call starts with its supplied context or a fresh empty context.
     * Nested calls are independent.
     *
     * @template TResult
     *
     * @param Query<TResult> $query The query to answer.
     * @param DispatchContext $context The application-defined execution metadata.
     *
     * @return TResult The query result.
     *
     * @throws QueryHandlerNotFoundException When the query reaching the handler stage has no registration.
     * @throws Throwable When execution fails, propagated unchanged unless intercepted by middleware.
     */
    public function ask(Query $query, DispatchContext $context = new DispatchContext()): mixed
    {
        return $this->execution->execute($query, $context);
    }
}
