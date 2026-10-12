<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Help;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;
use ExtendsSoftware\ExaPHP\Cli\Help\HelpRenderer;
use PHPUnit\Framework\TestCase;

final class HelpRendererTest extends TestCase
{
    public function testListsCommandsInRegistrationOrderWithDescriptions(): void
    {
        $text = new HelpRenderer()->commandList([
            new CommandDefinition('zebra', 'missing', 'First command'),
            new CommandDefinition('article:create', 'missing'),
        ], 'bin/console.php');
        self::assertStringContainsString("Commands:\n  zebra  First command\n  article:create\n", $text);
        self::assertStringContainsString('Usage: php bin/console.php <command> [arguments] [options]', $text);
        self::assertStringEndsWith("\n", $text);
    }

    public function testExplainsEmptyRegistry(): void
    {
        self::assertStringContainsString('No commands registered.', new HelpRenderer()->commandList([], 'console.php'));
    }

    public function testRendersArgumentModesOptionAliasesAndValueRequirements(): void
    {
        $definition = new CommandDefinition('greet', 'missing', 'Say hello', [
            new ArgumentDefinition('name', 'Recipient'),
            new ArgumentDefinition('suffix', mode: ArgumentMode::Optional),
        ], [
            new OptionDefinition('quiet', 'Suppress output', shortAlias: 'q'),
            new OptionDefinition('format', 'Output format', OptionMode::RequiredValue, 'f'),
            new OptionDefinition('verbose'),
        ]);
        $text = new HelpRenderer()->commandHelp($definition, 'console.php');
        self::assertStringContainsString('Usage: php console.php greet <name> [suffix] [options]', $text);
        self::assertStringContainsString("\nSay hello\n", $text);
        self::assertStringContainsString('name (required)  Recipient', $text);
        self::assertStringContainsString('suffix (optional)', $text);
        self::assertStringContainsString('-q, --quiet  Suppress output', $text);
        self::assertStringContainsString('-f, --format <value>  Output format', $text);
        self::assertStringContainsString("  --verbose\n", $text);
        self::assertStringEndsWith("\n", $text);
    }

    public function testOmitsEmptyArgumentAndOptionSections(): void
    {
        $text = new HelpRenderer()->commandHelp(new CommandDefinition('greet', 'missing'), 'console.php');
        self::assertStringNotContainsString('Arguments:', $text);
        self::assertStringNotContainsString('Options:', $text);
        self::assertStringNotContainsString('[options]', $text);
    }
}
