<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Input;

use ExtendsSoftware\ExaPHP\Cli\Input\Exception\InvalidInputException;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testPreservesParsedStringsFlagsAndNames(): void
    {
        $input = new Input('articles:create', ['title' => '', 'id' => '0042'], [
            'author' => '42', 'publish' => true, 'quiet' => false, 'label' => '',
        ]);
        $this->assertSame('articles:create', $input->command);
        $this->assertSame(['title' => '', 'id' => '0042'], $input->arguments);
        $this->assertSame(['author' => '42', 'publish' => true, 'quiet' => false, 'label' => ''], $input->options);
        $this->assertArrayNotHasKey('missing', $input->options);
    }

    public function testArgumentsAndOptionsDefaultToEmptyMaps(): void
    {
        $input = new Input('help');
        $this->assertSame([], $input->arguments);
        $this->assertSame([], $input->options);
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<array-key, mixed> $options
     */
    #[DataProvider('invalidInput')]
    public function testRejectsMalformedInput(string $command, array $arguments, array $options): void
    {
        $this->expectException(InvalidInputException::class);
        new Input($command, $arguments, $options);
    }

    /** @return iterable<array{string, array<array-key, mixed>, array<array-key, mixed>}> */
    public static function invalidInput(): iterable
    {
        yield ['', [], []];
        yield ['run', ['' => 'value'], []];
        yield ['run', ['value'], []];
        yield ['run', ['id' => 42], []];
        yield ['run', ['id' => null], []];
        yield ['run', [], ['' => true]];
        yield ['run', [], [true]];
        yield ['run', [], ['flag' => null]];
        yield ['run', [], ['count' => 42]];
        yield ['run', [], ['repeat' => ['a', 'b']]];
    }
}
