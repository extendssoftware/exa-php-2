<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Input\Parser;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentMode;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\OptionMode;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\ArgvInputParser;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\DuplicateOptionException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\InvalidTokensException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnknownOptionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ArgvInputParserTest extends TestCase
{
    /**
     * @param list<string> $tokens
     * @param array<string, string> $arguments
     * @param array<string, string|bool> $options
     */
    #[DataProvider('validInput')]
    public function testParsesNamedInput(array $tokens, array $arguments, array $options): void
    {
        $input = new ArgvInputParser()->parse($this->definition(), $tokens);
        $this->assertSame('articles:create', $input->command);
        $this->assertSame($arguments, $input->arguments);
        $this->assertSame($options, $input->options);
    }

    /** @return iterable<array{list<string>, array<string, string>, array<string, string|bool>}> */
    public static function validInput(): iterable
    {
        yield [['Title'], ['title' => 'Title'], []];
        yield [[''], ['title' => ''], []];
        yield [['My article', '--author=0042', '--publish'], ['title' => 'My article'], [
            'author' => '0042', 'publish' => true,
        ]];
        yield [['--publish', 'Title', '--author', '42', 'Summary'], [
            'title' => 'Title', 'summary' => 'Summary',
        ], ['publish' => true, 'author' => '42']];
        yield [['-a', '42', 'Title', '-p'], ['title' => 'Title'], ['author' => '42', 'publish' => true]];
        yield [['Title', '--author='], ['title' => 'Title'], ['author' => '']];
        yield [['Title', '--author', ''], ['title' => 'Title'], ['author' => '']];
        yield [['Title', '--author=a=b'], ['title' => 'Title'], ['author' => 'a=b']];
        yield [['Title', '--author=-42'], ['title' => 'Title'], ['author' => '-42']];
        yield [['Title', '--author=--'], ['title' => 'Title'], ['author' => '--']];
        yield [['Title', '--author', '-'], ['title' => 'Title'], ['author' => '-']];
        yield [['--', '--publish', '--'], ['title' => '--publish', 'summary' => '--'], []];
        yield [['Title', '--', '-value'], ['title' => 'Title', 'summary' => '-value'], []];
        yield [['-'], ['title' => '-'], []];
        yield [['Title', 'a "quoted" value'], ['title' => 'Title', 'summary' => 'a "quoted" value'], []];
    }

    /**
     * @param array<array-key, mixed> $tokens
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidUsage(array $tokens, string $exception): void
    {
        $this->expectException($exception);
        new ArgvInputParser()->parse($this->definition(), $tokens);
    }

    /** @return iterable<array{array<array-key, mixed>, class-string<Throwable>}> */
    public static function invalidInput(): iterable
    {
        yield [[], MissingArgumentException::class];
        yield [['--publish'], MissingArgumentException::class];
        yield [['Title', 'Summary', 'Extra'], UnexpectedArgumentException::class];
        yield [['Title', '--unknown'], UnknownOptionException::class];
        yield [['Title', '-x'], UnknownOptionException::class];
        yield [['Title', '--Publish'], UnknownOptionException::class];
        yield [['Title', '-pa'], UnknownOptionException::class];
        yield [['Title', '-a42'], UnknownOptionException::class];
        yield [['Title', '-a=42'], UnknownOptionException::class];
        yield [['Title', '--author'], MissingOptionValueException::class];
        yield [['Title', '--author', '--publish'], MissingOptionValueException::class];
        yield [['Title', '--author', '--'], MissingOptionValueException::class];
        yield [['Title', '-a', '-42'], MissingOptionValueException::class];
        yield [['Title', '--publish=false'], UnexpectedOptionValueException::class];
        yield [['Title', '--publish='], UnexpectedOptionValueException::class];
        yield [['Title', '--publish', '-p'], DuplicateOptionException::class];
        yield [['Title', '-a', '42', '--author=7'], DuplicateOptionException::class];
        yield [['name' => 'Title'], InvalidTokensException::class];
        yield [[42], InvalidTokensException::class];
    }

    public function testRepeatedCallsHaveIndependentParsingState(): void
    {
        $parser = new ArgvInputParser();
        $definition = $this->definition();
        $first = $parser->parse($definition, ['--', '--publish']);
        $second = $parser->parse($definition, ['Title', '--publish']);
        $this->assertSame([], $first->options);
        $this->assertSame(['publish' => true], $second->options);
    }

    private function definition(): CommandDefinition
    {
        return new CommandDefinition('articles:create', 'handler', arguments: [
            new ArgumentDefinition('title'),
            new ArgumentDefinition('summary', mode: ArgumentMode::Optional),
        ], options: [
            new OptionDefinition('author', mode: OptionMode::RequiredValue, shortAlias: 'a'),
            new OptionDefinition('publish', shortAlias: 'p'),
        ]);
    }
}
