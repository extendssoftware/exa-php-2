<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Cli\ErrorHandling;

use ExtendsSoftware\ExaPHP\Cli\ErrorHandling\DefaultExceptionPresenter;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\DuplicateOptionException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\InvalidTokensException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\MissingOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedArgumentException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnexpectedOptionValueException;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\Exception\UnknownOptionException;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\Exception\CommandNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class DefaultExceptionPresenterTest extends TestCase
{
    /** @param class-string<Throwable> $type */
    #[DataProvider('usageFailures')]
    public function testPresentsUsageFailures(string $type): void
    {
        $output = $this->createMock(Output::class);
        $output->expects($this->never())->method('write');
        $output->expects($this->once())->method('writeError')->with("Error: Invalid input.\n");
        $this->assertSame(2, new DefaultExceptionPresenter()->present(new $type('Invalid input.'), $output));
    }

    /** @return iterable<array{class-string<Throwable>}> */
    public static function usageFailures(): iterable
    {
        yield [DuplicateOptionException::class];
        yield [InvalidTokensException::class];
        yield [MissingArgumentException::class];
        yield [MissingOptionValueException::class];
        yield [UnexpectedArgumentException::class];
        yield [UnexpectedOptionValueException::class];
        yield [UnknownOptionException::class];
        yield [CommandNotFoundException::class];
    }

    public function testDoesNotExposeUnexpectedFailureDetails(): void
    {
        $output = $this->createMock(Output::class);
        $output->expects($this->never())->method('write');
        $output->expects($this->once())->method('writeError')->with("Error: Command execution failed.\n");
        $failure = new TypeError('secret', 99, new RuntimeException('previous secret'));
        $this->assertSame(1, new DefaultExceptionPresenter()->present($failure, $output));
    }

    public function testKeepsUsagePresentationOnOneLine(): void
    {
        $output = $this->createMock(Output::class);
        $output->expects($this->once())->method('writeError')->with("Error: bad  option [31m\n");
        new DefaultExceptionPresenter()->present(new UnknownOptionException("bad\r\noption\e[31m"), $output);
    }

    public function testPropagatesOutputFailureUnchanged(): void
    {
        $failure = new RuntimeException('stderr failed');
        $output = $this->createStub(Output::class);
        $output->method('writeError')->willThrowException($failure);
        try {
            new DefaultExceptionPresenter()->present(new TypeError('execution'), $output);
            $this->fail('Expected output failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
    }
}
