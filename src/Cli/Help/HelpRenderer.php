<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Cli\Help;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;

use function implode;

/**
 * Renders plain-text command listings and usage without resolving handlers.
 */
final readonly class HelpRenderer
{
    /**
     * Renders available commands in registration order with a trailing newline.
     *
     * @param list<CommandDefinition> $commands The available command definitions.
     * @param string $script The invocation label, such as `bin/console.php`.
     *
     * @return string The listing, including global help usage and descriptions.
     */
    public function commandList(array $commands, string $script): string
    {
        $lines = [
            'Usage: php ' . $script . ' <command> [arguments] [options]',
            '',
            'Commands:',
        ];
        foreach ($commands as $command) {
            $lines[] = '  ' . $command->name . ($command->description === '' ? '' : '  ' . $command->description);
        }
        if ($commands === []) {
            $lines[] = '  No commands registered.';
        }
        $lines[] = '';
        $lines[] = 'Use php ' . $script . ' <command> --help for command help.';
        $lines[] = 'Use --list, --help, or -h to list commands.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Renders command usage, arguments, and options with a trailing newline.
     *
     * @param CommandDefinition $command The selected command definition.
     * @param string $script The invocation label, such as `bin/console.php`.
     *
     * @return string The command's plain-text help.
     */
    public function commandHelp(CommandDefinition $command, string $script): string
    {
        $usage = 'Usage: php ' . $script . ' ' . $command->name;
        foreach ($command->arguments as $argument) {
            $usage .= $argument->mode === ArgumentMode::Required
                ? ' <' . $argument->name . '>' : ' [' . $argument->name . ']';
        }
        if ($command->options !== []) {
            $usage .= ' [options]';
        }
        $lines = [$usage];
        if ($command->description !== '') {
            $lines[] = '';
            $lines[] = $command->description;
        }
        if ($command->arguments !== []) {
            $lines[] = '';
            $lines[] = 'Arguments:';
            foreach ($command->arguments as $argument) {
                $mode = $argument->mode === ArgumentMode::Required ? 'required' : 'optional';
                $lines[] = '  ' . $argument->name . ' (' . $mode . ')'
                    . ($argument->description === '' ? '' : '  ' . $argument->description);
            }
        }
        if ($command->options !== []) {
            $lines[] = '';
            $lines[] = 'Options:';
            foreach ($command->options as $option) {
                $label = $option->shortAlias === null ? '' : '-' . $option->shortAlias . ', ';
                $label .= '--' . $option->name;
                if ($option->mode === OptionMode::RequiredValue) {
                    $label .= ' <value>';
                }
                $lines[] = '  ' . $label . ($option->description === '' ? '' : '  ' . $option->description);
            }
        }
        $lines[] = '';
        $lines[] = 'Use ' . $command->name . ' --help or ' . $command->name . ' -h without other tokens for help.';
        $lines[] = 'Use -- to end options; option values beginning with - require --name=value.';

        return implode("\n", $lines) . "\n";
    }
}
