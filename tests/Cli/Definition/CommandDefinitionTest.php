<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Definition;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidArgumentDefinitionException;
use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidCommandDefinitionException;
use ExtendsSoftware\ExaPHP\Cli\Definition\Exception\InvalidOptionDefinitionException;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CommandDefinitionTest extends TestCase
{
    public function testRetainsOrderedDefinitionsAndUnresolvedHandlerIdentifier(): void
    {
        $arguments = [new ArgumentDefinition('title'), new ArgumentDefinition('author', mode: ArgumentMode::Optional)];
        $options = [
            new OptionDefinition('publish'),
            new OptionDefinition('author', mode: OptionMode::RequiredValue, shortAlias: 'a'),
        ];
        $command = new CommandDefinition(
            'articles:create', 'unregistered-handler', 'Create an article', $arguments, $options,
        );
        $this->assertSame('articles:create', $command->name);
        $this->assertSame('unregistered-handler', $command->handlerId);
        $this->assertSame('Create an article', $command->description);
        $this->assertSame($arguments, $command->arguments);
        $this->assertSame($options, $command->options);
        $this->assertSame(OptionMode::Flag, $options[0]->mode);
        $this->assertSame('a', $options[1]->shortAlias);
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<array-key, mixed> $options
     */
    #[DataProvider('invalidCommands')]
    public function testRejectsInvalidCommandDefinitions(string $name, string $id, array $arguments, array $options): void
    {
        $this->expectException(InvalidCommandDefinitionException::class);
        new CommandDefinition($name, $id, arguments: $arguments, options: $options);
    }

    /** @return iterable<array{string, string, array<array-key, mixed>, array<array-key, mixed>}> */
    public static function invalidCommands(): iterable
    {
        yield ['', 'handler', [], []];
        yield ['-run', 'handler', [], []];
        yield ['run::task', 'handler', [], []];
        yield ['run task', 'handler', [], []];
        yield ['run', '', [], []];
        yield ['run', 'handler', ['named' => new ArgumentDefinition('name')], []];
        yield ['run', 'handler', [], ['named' => new OptionDefinition('name')]];
        yield ['run', 'handler', [new stdClass()], []];
        yield ['run', 'handler', [], [new stdClass()]];
        yield ['run', 'handler', [new ArgumentDefinition('name'), new ArgumentDefinition('name')], []];
        yield ['run', 'handler', [
            new ArgumentDefinition('first', mode: ArgumentMode::Optional), new ArgumentDefinition('second'),
        ], []];
        yield ['run', 'handler', [], [new OptionDefinition('name'), new OptionDefinition('name')]];
        yield ['run', 'handler', [], [
            new OptionDefinition('first', shortAlias: 'a'), new OptionDefinition('second', shortAlias: 'a'),
        ]];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidArgumentNames(string $name): void
    {
        $this->expectException(InvalidArgumentDefinitionException::class);
        new ArgumentDefinition($name);
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidOptionNames(string $name): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        new OptionDefinition($name);
    }

    /** @return iterable<array{string}> */
    public static function invalidNames(): iterable
    {
        yield [''];
        yield ['--name'];
        yield ['1'];
        yield ['two words'];
        yield ['name=value'];
    }

    #[DataProvider('invalidAliases')]
    public function testRejectsInvalidShortAliases(string $alias): void
    {
        $this->expectException(InvalidOptionDefinitionException::class);
        new OptionDefinition('name', shortAlias: $alias);
    }

    /** @return iterable<array{string}> */
    public static function invalidAliases(): iterable
    {
        yield [''];
        yield ['ab'];
        yield ['-a'];
        yield ['1'];
    }
}
