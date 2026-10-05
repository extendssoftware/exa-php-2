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
        $this->assertStringContainsString("Commands:\n  zebra  First command\n  article:create\n", $text);
        $this->assertStringContainsString('Usage: php bin/console.php <command> [arguments] [options]', $text);
        $this->assertStringEndsWith("\n", $text);
    }

    public function testExplainsEmptyRegistry(): void
    {
        $this->assertStringContainsString('No commands registered.', new HelpRenderer()->commandList([], 'console.php'));
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
        $this->assertStringContainsString('Usage: php console.php greet <name> [suffix] [options]', $text);
        $this->assertStringContainsString("\nSay hello\n", $text);
        $this->assertStringContainsString('name (required)  Recipient', $text);
        $this->assertStringContainsString('suffix (optional)', $text);
        $this->assertStringContainsString('-q, --quiet  Suppress output', $text);
        $this->assertStringContainsString('-f, --format <value>  Output format', $text);
        $this->assertStringContainsString("  --verbose\n", $text);
        $this->assertStringEndsWith("\n", $text);
    }

    public function testOmitsEmptyArgumentAndOptionSections(): void
    {
        $text = new HelpRenderer()->commandHelp(new CommandDefinition('greet', 'missing'), 'console.php');
        $this->assertStringNotContainsString('Arguments:', $text);
        $this->assertStringNotContainsString('Options:', $text);
        $this->assertStringNotContainsString('[options]', $text);
    }
}
