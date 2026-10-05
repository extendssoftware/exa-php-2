<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command;

use Override;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\ClosureCommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandExecution;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Middleware\CommandMiddleware;
use ExtendsSoftware\ExaPHP\Cqrs\DispatchContext;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Exception\InvalidCommandMiddlewareException;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Exception\CommandHandlerNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Exception\DuplicateCommandHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Command\Exception\InvalidCommandRegistrationException;
use ReflectionClass;
use Throwable;

use function array_is_list;
use function array_key_exists;
use function array_reverse;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Dispatches commands immediately to handlers registered for their exact class.
 *
 * Registrations are fixed after construction and handler instances are reused. Class aliases and differently cased
 * names are normalized. Registration validates the runtime contracts, not handler PHPDoc generic bindings.
 */
final readonly class SynchronousCommandBus implements CommandBus
{
    /**
     * Handler instances indexed by canonical command class.
     *
     * @var array<class-string<Command>, CommandHandler>
     */
    private array $handlers;

    /**
     * Fixed execution chain, reused without retaining per-dispatch context.
     */
    private CommandExecution $execution;

    /**
     * Creates a bus with validated command handler registrations.
     *
     * @param array<class-string<Command>, CommandHandler> $handlers Handlers indexed by concrete command class.
     * @param list<CommandMiddleware> $middleware Middleware in execution order, first entry outermost.
     *
     * @throws InvalidCommandRegistrationException When a key is not a concrete Command class or a handler is invalid.
     * @throws DuplicateCommandHandlerException When multiple keys identify the same canonical command class.
     * @throws InvalidCommandMiddlewareException When middleware is not a list of CommandMiddleware instances.
     */
    public function __construct(array $handlers, array $middleware = [])
    {
        $registrations = [];
        foreach ($handlers as $commandClass => $handler) {
            if (!is_string($commandClass) || $commandClass === '') {
                throw new InvalidCommandRegistrationException(
                    'Command registration keys must be non-empty class names.',
                );
            }

            try {
                $class = new ReflectionClass($commandClass);
            } catch (Throwable $exception) {
                throw new InvalidCommandRegistrationException(
                    sprintf('Could not inspect registered command class "%s".', $commandClass),
                    0,
                    $exception,
                );
            }

            if ($class->isInterface() || $class->isAbstract() || !$class->implementsInterface(Command::class)) {
                throw new InvalidCommandRegistrationException(
                    sprintf('Registered class "%s" must be a concrete Command.', $commandClass),
                );
            }

            if (!$handler instanceof CommandHandler) {
                throw new InvalidCommandRegistrationException(
                    sprintf(
                        'Handler for command "%s" must implement CommandHandler, %s given.',
                        $commandClass,
                        get_debug_type($handler),
                    ),
                );
            }

            $name = $class->getName();
            if (array_key_exists($name, $registrations)) {
                throw new DuplicateCommandHandlerException(
                    sprintf('A handler is already registered for command "%s".', $name),
                );
            }

            $registrations[$name] = $handler;
        }

        $this->handlers = $registrations;
        if (!array_is_list($middleware)) {
            throw new InvalidCommandMiddlewareException('Command middleware must be a list.');
        }
        foreach ($middleware as $entry) {
            if (!$entry instanceof CommandMiddleware) {
                throw new InvalidCommandMiddlewareException(
                    'Each command middleware must implement CommandMiddleware.',
                );
            }
        }

        $execution = new ClosureCommandExecution(function (Command $command, DispatchContext $context): void {
            $class = $command::class;
            if (!isset($this->handlers[$class])) {
                throw new CommandHandlerNotFoundException(
                    sprintf('No handler is registered for command "%s".', $class),
                );
            }
            $this->handlers[$class]->handle($command);
        });
        foreach (array_reverse($middleware) as $entry) {
            $next = $execution;
            $execution = new ClosureCommandExecution(
                static function (Command $command, DispatchContext $context) use ($entry, $next): void {
                    $entry->process($command, $context, $next);
                },
            );
        }
        $this->execution = $execution;
    }

    /**
     * Executes middleware followed by the handler for the command's exact class.
     *
     * Middleware may short-circuit or forward a replacement command and context. Handler lookup occurs only at the
     * end of the chain. Each call starts with its supplied context or a fresh empty context.
     * Nested calls are independent.
     *
     * @param Command $command The command to dispatch.
     * @param DispatchContext $context The application-defined execution metadata.
     *
     * @return void
     *
     * @throws CommandHandlerNotFoundException When the command reaching the handler stage has no registration.
     * @throws Throwable When execution fails, propagated unchanged unless intercepted by middleware.
     */
    #[Override]
    public function dispatch(Command $command, DispatchContext $context = new DispatchContext()): void
    {
        $this->execution->execute($command, $context);
    }
}
