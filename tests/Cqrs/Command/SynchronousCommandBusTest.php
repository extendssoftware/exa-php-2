<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cqrs\Command;

use ExtendsSoftware\ExaPHP\Cqrs\Command\Command;
use ExtendsSoftware\ExaPHP\Cqrs\Command\CommandHandler;
use ExtendsSoftware\ExaPHP\Cqrs\Command\SynchronousCommandBus;
use ExtendsSoftware\ExaPHP\Cqrs\CqrsException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\CommandHandlerNotFoundException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\DuplicateCommandHandlerException;
use ExtendsSoftware\ExaPHP\Cqrs\Exception\InvalidCommandRegistrationException;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Fixture\ChildCommand;
use ExtendsSoftware\ExaPHP\Tests\Cqrs\Command\Fixture\ParentCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function class_alias;
use function get_debug_type;
use function preg_quote;
use function strtolower;

final class SynchronousCommandBusTest extends TestCase
{
    public function testDispatchesImmediatelyAndReusesRegisteredHandlers(): void
    {
        $command = new ParentCommand();
        $child = new ChildCommand();
        $handled = [];
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::exactly(2))->method('handle')->with(self::identicalTo($command))
            ->willReturnCallback(static function (Command $received) use (&$handled): void {
                $handled[] = $received;
            });
        $childHandler = $this->createMock(CommandHandler::class);
        $childHandler->expects(self::once())->method('handle')->with(self::identicalTo($child));

        $bus = new SynchronousCommandBus([
            ParentCommand::class => $handler,
            ChildCommand::class => $childHandler,
        ]);

        self::assertSame([], $handled);
        $bus->dispatch($command);
        self::assertSame([$command], $handled);
        $bus->dispatch($child);
        $bus->dispatch($command);
        self::assertSame([$command, $command], $handled);
    }

    public function testMissingRegistrationReportsTheCommandClass(): void
    {
        try {
            new SynchronousCommandBus([])->dispatch(new ParentCommand());
            self::fail('Expected a missing handler failure.');
        } catch (CommandHandlerNotFoundException $exception) {
            self::assertInstanceOf(CqrsException::class, $exception);
            self::assertStringContainsString(ParentCommand::class, $exception->getMessage());
        }
    }

    public function testDoesNotFallBackToAParentCommandHandler(): void
    {
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::never())->method('handle');
        $bus = new SynchronousCommandBus([ParentCommand::class => $handler]);

        $this->expectException(CommandHandlerNotFoundException::class);
        $bus->dispatch(new ChildCommand());
    }

    #[DataProvider('invalidCommandClasses')]
    public function testRejectsInvalidCommandClasses(int|string $class): void
    {
        $this->expectException(InvalidCommandRegistrationException::class);
        new SynchronousCommandBus([$class => $this->createStub(CommandHandler::class)]);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidCommandClasses(): iterable
    {
        yield 'integer key' => [0];
        yield 'empty key' => [''];
        yield 'unrelated class' => [stdClass::class];
        yield 'interface' => [Command::class];
        yield 'abstract class' => [TestCase::class];
        yield 'missing class' => ['ExaPHP\\MissingCommand'];
    }

    public function testPreservesTheCauseOfClassInspectionFailures(): void
    {
        try {
            new SynchronousCommandBus(['ExaPHP\\MissingCommand' => $this->createStub(CommandHandler::class)]);
            self::fail('Expected an invalid registration failure.');
        } catch (InvalidCommandRegistrationException $exception) {
            self::assertInstanceOf(CqrsException::class, $exception);
            self::assertInstanceOf(ReflectionException::class, $exception->getPrevious());
        }
    }

    #[DataProvider('invalidHandlers')]
    public function testRejectsInvalidHandlers(mixed $handler): void
    {
        $this->expectException(InvalidCommandRegistrationException::class);
        $this->expectExceptionMessageMatches(
            '/\AHandler for command "' . preg_quote(ParentCommand::class, '/')
            . '" must implement CommandHandler, ' . preg_quote(get_debug_type($handler), '/') . ' given\.\z/',
        );
        new SynchronousCommandBus([ParentCommand::class => $handler]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidHandlers(): iterable
    {
        yield 'null' => [null];
        yield 'boolean' => [false];
        yield 'object' => [new stdClass()];
        yield 'class name' => [CommandHandler::class];
        yield 'callable' => [static function (): void {
        }];
    }

    public function testNormalizesClassNameCasing(): void
    {
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle');
        new SynchronousCommandBus([strtolower(ParentCommand::class) => $handler])->dispatch(new ParentCommand());
    }

    public function testNormalizesClassAliases(): void
    {
        $alias = __NAMESPACE__ . '\\AliasedCommand';
        class_alias(ParentCommand::class, $alias);
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle');
        new SynchronousCommandBus([$alias => $handler])->dispatch(new ParentCommand());
    }

    public function testRejectsDuplicateCanonicalRegistrations(): void
    {
        $handler = $this->createStub(CommandHandler::class);
        $this->expectException(DuplicateCommandHandlerException::class);
        new SynchronousCommandBus([
            ParentCommand::class => $handler,
            strtolower(ParentCommand::class) => $handler,
        ]);
    }

    #[DataProvider('handlerFailures')]
    public function testPropagatesHandlerFailuresUnchangedAndCanDispatchAgain(Throwable $failure): void
    {
        $handler = $this->createMock(CommandHandler::class);
        $attempts = 0;
        $handler->expects(self::exactly(2))->method('handle')
            ->willReturnCallback(static function () use (&$attempts, $failure): void {
                if (++$attempts === 1) {
                    throw $failure;
                }
            });
        $bus = new SynchronousCommandBus([ParentCommand::class => $handler]);

        try {
            $bus->dispatch(new ParentCommand());
            self::fail('Expected the handler failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }

        $bus->dispatch(new ParentCommand());
        self::assertSame(2, $attempts);
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function handlerFailures(): iterable
    {
        yield 'domain exception' => [new RuntimeException('Domain failure')];
        yield 'engine error' => [new TypeError('Handler error')];
        yield 'component exception' => [new CommandHandlerNotFoundException('Nested dispatch failed')];
    }

    public function testChangingTheInputMapDoesNotChangeRegistrations(): void
    {
        $handler = $this->createMock(CommandHandler::class);
        $handler->expects(self::once())->method('handle');
        $registrations = [ParentCommand::class => &$handler];
        $bus = new SynchronousCommandBus($registrations);
        $handler = $this->createStub(CommandHandler::class);
        $registrations = [];

        $bus->dispatch(new ParentCommand());
    }
}
