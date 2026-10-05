<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation\String;

use ErrorException;
use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\InvalidPatternException;
use ExtendsSoftware\ExaPHP\Processing\Validation\Exception\PatternExecutionException;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\MatchesPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;
use function str_repeat;

final class MatchesPatternTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        $this->assertSame('not_string', MatchesPattern::CODE_NOT_STRING);
        $this->assertSame('pattern_mismatch', MatchesPattern::CODE_PATTERN_MISMATCH);
        $this->assertSame('invalid_utf8', MatchesPattern::CODE_INVALID_UTF8);
    }

    #[DataProvider('inputs')]
    public function testMatchesSuppliedPattern(string $pattern, mixed $value, ?string $code): void
    {
        $result = new MatchesPattern($pattern)->validate($value);
        $this->assertSame($code === null, $result->isValid());
        if ($code !== null) {
            $this->assertSame($code, $result->violations()[0]->code());
        }
    }

    /**
     * @return iterable<array{string, mixed, ?string}>
     */
    public static function inputs(): iterable
    {
        yield ['/abc/i', 'xxABCyy', null];
        yield ['/\Aabc\z/', 'xxabc', MatchesPattern::CODE_PATTERN_MISMATCH];
        yield ['/\A\z/', '', null];
        yield ['/./u', 'é', null];
        yield ['/./u', "\xFF", MatchesPattern::CODE_INVALID_UTF8];
        yield ['/./', "\xFF", null];
        yield ['/./', null, MatchesPattern::CODE_NOT_STRING];
    }

    public function testInvalidPatternRetainsCauseAndRestoresErrorHandler(): void
    {
        $handler = static fn(): bool => false;
        set_error_handler($handler);
        try {
            try {
                new MatchesPattern('/[/');
                $this->fail('Expected invalid pattern.');
            } catch (InvalidPatternException $exception) {
                $this->assertInstanceOf(ErrorException::class, $exception->getPrevious());
            }
            $previous = set_error_handler($handler);
            restore_error_handler();
            $this->assertSame($handler, $previous);
        } finally {
            restore_error_handler();
        }
    }

    public function testExecutionLimitIsAnException(): void
    {
        $validator = new MatchesPattern('/(*NO_JIT)(*LIMIT_MATCH=1)\A(a+)+\z/');
        $this->expectException(PatternExecutionException::class);
        $validator->validate(str_repeat('a', 100) . '!');
    }
}
