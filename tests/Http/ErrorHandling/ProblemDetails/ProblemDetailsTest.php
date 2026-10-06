<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\ErrorHandling\ProblemDetails;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\Exception\InvalidProblemDetailsException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use const INF;

final class ProblemDetailsTest extends TestCase
{
    public function testGenericProblemOmitsOptionalMembers(): void
    {
        $this->assertSame(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404],
            new ProblemDetails(StatusCode::NotFound, 'Not Found')->toArray());
    }

    public function testCustomProblemRetainsTypeOccurrenceAndExtensions(): void
    {
        $problem = new ProblemDetails(
            StatusCode::UnprocessableContent,
            'Invalid article',
            new Uri('https://example.com/problems/invalid-article'),
            '',
            new Uri('/articles/123'),
            ['errors' => [['field' => 'title', 'code' => 'required']], 'retry' => false],
        );
        $this->assertSame([
            'type' => 'https://example.com/problems/invalid-article',
            'title' => 'Invalid article',
            'status' => 422,
            'detail' => '',
            'instance' => '/articles/123',
            'errors' => [['field' => 'title', 'code' => 'required']],
            'retry' => false,
        ], $problem->toArray());
    }

    public function testDetachesCallerOwnedReferences(): void
    {
        $value = 'original';
        $entries = ['value' => &$value];
        $problem = new ProblemDetails(StatusCode::BadRequest, 'Bad Request', extensions: ['entries' => &$entries]);
        $value = 'changed';
        $entries['another'] = 'added';
        $this->assertSame(['entries' => ['value' => 'original']], $problem->extensions);
    }

    /** @param array<array-key, mixed> $extensions */
    #[DataProvider('invalidExtensions')]
    public function testRejectsInvalidExtensions(array $extensions): void
    {
        $this->expectException(InvalidProblemDetailsException::class);
        new ProblemDetails(StatusCode::BadRequest, 'Bad Request', extensions: $extensions);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidExtensions(): iterable
    {
        foreach (['type', 'title', 'status', 'detail', 'instance', ''] as $key) {
            yield [[$key => 'override']];
        }
        yield [[0 => 'value']];
        yield [['value' => new stdClass()]];
        yield [['value' => INF]];
        $recursive = [];
        $recursive['self'] = &$recursive;
        yield [['value' => $recursive]];
    }
}
