<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cqrs\Command;

use ExtendsSoftware\ExaPHP\Cqrs\Exception\CommandHandlerNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\DuplicateCommandHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidCommandRegistrationException;
use ReflectionClass;
use Throwable;

use function array_key_exists;
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
     * Creates a bus with validated command handler registrations.
     *
     * @param array<class-string<Command>, CommandHandler> $handlers Handlers indexed by concrete command class.
     *
     * @throws InvalidCommandRegistrationException When a key is not a concrete Command class or a handler is invalid.
     * @throws DuplicateCommandHandlerException When multiple keys identify the same canonical command class.
     */
    public function __construct(array $handlers)
    {
        $registrations = [];
        foreach ($handlers as $commandClass => $handler) {
            if (!is_string($commandClass) || $commandClass === '') {
                throw new InvalidCommandRegistrationException('Command registration keys must be non-empty class names.');
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
    }

    /**
     * Invokes the handler for the command's exact class and waits for it to complete.
     *
     * Parent classes and interfaces are not considered. The original command object is passed to the handler.
     * Handler exceptions and engine errors propagate unchanged.
     *
     * @param Command $command The command to dispatch.
     *
     * @return void
     *
     * @throws CommandHandlerNotFoundException When the exact command class has no registered handler.
     * @throws Throwable When the handler fails, propagated unchanged.
     */
    public function dispatch(Command $command): void
    {
        $class = $command::class;
        if (!isset($this->handlers[$class])) {
            throw new CommandHandlerNotFoundException(sprintf('No handler is registered for command "%s".', $class));
        }

        $this->handlers[$class]->handle($command);
    }
}
