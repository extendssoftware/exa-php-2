<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Routing;

use ExtendsSoftware\ExaPHP\Cli\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\InputParser;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use Throwable;

/**
 * Dispatches a selected CLI command through parsing, lazy handler resolution, and execution.
 */
final readonly class CommandDispatcher
{
    /**
     * Creates a dispatcher with explicit command lookup, parsing, and resolution boundaries.
     *
     * @param CommandRegistry $registry The available command definitions.
     * @param InputParser $parser The parser for the selected definition.
     * @param HandlerResolver $resolver The handler resolver, invoked only after successful parsing.
     */
    public function __construct(
        private CommandRegistry $registry,
        private InputParser $parser,
        private HandlerResolver $resolver,
    ) {
    }

    /**
     * Selects a command, parses tokens, resolves its handler, and returns the handler's exit code.
     *
     * Failures propagate unchanged. The dispatcher neither writes error messages nor terminates the process.
     *
     * @param string $name The case-sensitive command name.
     * @param list<string> $tokens Argument tokens excluding the script and command name.
     * @param Output $output The output channels supplied to the handler.
     *
     * @return int The handler's exit code, including application-specific codes.
     *
     * @throws Throwable When lookup, parsing, resolution, or execution fails, propagated unchanged.
     */
    public function dispatch(string $name, array $tokens, Output $output): int
    {
        $definition = $this->registry->get($name);
        $input = $this->parser->parse($definition, $tokens);
        $handler = $this->resolver->resolve($definition->handlerId);

        return $handler->handle($input, $output);
    }
}
