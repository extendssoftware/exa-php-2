<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Authentication\Bearer;

use ExtendsSoftware\ExaPHP\Authentication\Bearer\BearerTokenCredentials;
use ExtendsSoftware\ExaPHP\Authentication\Credentials;
use ExtendsSoftware\ExaPHP\Authentication\Exception\InvalidCredentialsException;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function ob_get_clean;
use function ob_start;
use function var_dump;
use function print_r;
use function serialize;

final class BearerTokenCredentialsTest extends TestCase
{
    public function testPreservesTokenAndRedactsDebugOutput(): void
    {
        $token = 'Secret.a-b_c~d+e/f==';
        $credentials = new BearerTokenCredentials($token);
        self::assertInstanceOf(Credentials::class, $credentials);
        self::assertSame($token, $credentials->token());
        ob_start();
        var_dump($credentials);
        $output = ob_get_clean();
        self::assertStringNotContainsString($token, $output);
        self::assertStringNotContainsString($token, print_r($credentials, true));
    }

    public function testPreventsSerializationOfCredentials(): void
    {
        $credentials = new BearerTokenCredentials('secret-token');
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs("Serialization of 'SensitiveParameterValue' is not allowed");

        serialize($credentials);
    }

    #[DataProvider('invalidTokens')]
    public function testRejectsInvalidTokenSyntaxWithoutExposingIt(string $token): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessageIs('Invalid Bearer token syntax.');
        new BearerTokenCredentials($token);
    }

    /** @return iterable<array{string}> */
    public static function invalidTokens(): iterable
    {
        foreach (['', '=', 'token=middle', ' token', 'token ', 'two tokens', "token\n", 'token,other', 'é'] as $token) {
            yield [$token];
        }
    }
}
