<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\Routing;

use ExtendsSoftware\ExaPHP\Cli\Definition\ArgumentDefinition;
use ExtendsSoftware\ExaPHP\Cli\Definition\CommandDefinition;
use ExtendsSoftware\ExaPHP\Cli\Handler\CommandHandler;
use ExtendsSoftware\ExaPHP\Cli\Handler\Exception\HandlerResolutionException;
use ExtendsSoftware\ExaPHP\Cli\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Cli\Input\Input;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\ArgvInputParser;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\InputParser;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use ExtendsSoftware\ExaPHP\Cli\Routing\RegisteredCommands;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

final class CommandDispatcherTest extends TestCase
{
    public function testParsesSelectedCommandResolvesOnlyItsHandlerAndReturnsExitCode(): void
    {
        $registry = new RegisteredCommands([
            new CommandDefinition('unused', 'unused'),
            new CommandDefinition('greet', 'greeting', arguments: [new ArgumentDefinition('name')]),
        ]);
        $output = $this->createStub(Output::class);
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects($this->once())->method('handle')->willReturnCallback(
            function (Input $input, Output $actualOutput) use ($output): int {
                $this->assertSame('greet', $input->command);
                $this->assertSame(['name' => 'Ada'], $input->arguments);
                $this->assertSame($output, $actualOutput);

                return 17;
            },
        );
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects($this->once())->method('resolve')->with('greeting')->willReturn($handler);
        $dispatcher = new CommandDispatcher($registry, new ArgvInputParser(), $resolver);
        $this->assertSame(17, $dispatcher->dispatch('greet', ['Ada'], $output));
    }

    public function testUnknownCommandDoesNotParseOrResolve(): void
    {
        $parser = $this->createMock(InputParser::class);
        $parser->expects($this->never())->method('parse');
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $dispatcher = new CommandDispatcher(new RegisteredCommands(), $parser, $resolver);
        $this->expectException(CommandNotFoundException::class);
        $dispatcher->dispatch('missing', [], $this->createStub(Output::class));
    }

    public function testInvalidInputDoesNotResolveHandler(): void
    {
        $registry = new RegisteredCommands([
            new CommandDefinition('greet', 'handler', arguments: [new ArgumentDefinition('name')]),
        ]);
        $resolver = $this->createMock(HandlerResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $dispatcher = new CommandDispatcher($registry, new ArgvInputParser(), $resolver);
        $this->expectException(MissingArgumentException::class);
        $dispatcher->dispatch('greet', [], $this->createStub(Output::class));
    }

    #[DataProvider('failures')]
    public function testResolutionAndExecutionFailuresPropagateUnchanged(
        Throwable $failure,
        bool $duringResolution,
    ): void
    {
        $handler = $this->createMock(CommandHandler::class);
        $resolver = $this->createMock(HandlerResolver::class);
        if ($duringResolution) {
            $resolver->expects($this->once())->method('resolve')->willThrowException($failure);
            $handler->expects($this->never())->method('handle');
        } else {
            $resolver->expects($this->once())->method('resolve')->willReturn($handler);
            $handler->expects($this->once())->method('handle')->willThrowException($failure);
        }
        $dispatcher = new CommandDispatcher(
            new RegisteredCommands([new CommandDefinition('run', 'handler')]), new ArgvInputParser(), $resolver,
        );
        try {
            $dispatcher->dispatch('run', [], $this->createStub(Output::class));
            $this->fail('Expected dispatch failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    /** @return iterable<array{Throwable, bool}> */
    public static function failures(): iterable
    {
        yield [new HandlerResolutionException('Resolution failure'), true];
        yield [new TypeError('Execution error'), false];
    }
}
