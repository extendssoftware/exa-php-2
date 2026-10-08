<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Authentication;

use ExtendsSoftware\ExaPHP\Authentication\AuthenticationException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\AuthenticationFailedException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use ExtendsSoftware\ExaPHP\Authentication\Exception\UnsupportedCredentialsException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class AuthenticationExceptionTest extends TestCase
{
    /**
     * @param class-string<Throwable> $exceptionClass
     * @param class-string<Throwable> $baseClass
     */
    #[DataProvider('exceptions')]
    public function testPreservesFailureContextAndExceptionContracts(string $exceptionClass, string $baseClass): void
    {
        $previous = new RuntimeException('Original failure.');
        $exception = new $exceptionClass('Authentication context.', 7, $previous);

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertInstanceOf($baseClass, $exception);
        self::assertSame('Authentication context.', $exception->getMessage());
        self::assertSame(7, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    /** @return iterable<string, array{class-string<Throwable>, class-string<Throwable>}> */
    public static function exceptions(): iterable
    {
        yield 'operational failure' => [AuthenticationFailedException::class, RuntimeException::class];
        yield 'rejected credentials' => [InvalidCredentialsException::class, RuntimeException::class];
        yield 'unsupported credentials' => [UnsupportedCredentialsException::class, InvalidArgumentException::class];
    }
}
