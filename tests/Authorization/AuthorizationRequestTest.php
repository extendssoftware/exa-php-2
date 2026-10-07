<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Authorization;

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationException;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;
use ExtendsSoftware\ExaPHP\Authorization\Exception\InvalidAuthorizationRequestException;
use ExtendsSoftware\ExaPHP\Identity\Actor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AuthorizationRequestTest extends TestCase
{
    public function testPreservesActorActionAndResourceWithoutNormalization(): void
    {
        $actor = new Actor('42', 'user');
        $resource = new stdClass();
        $request = new AuthorizationRequest($actor, ' Article.Update ', $resource);

        self::assertSame($actor, $request->actor);
        self::assertSame(' Article.Update ', $request->action);
        self::assertSame($resource, $request->resource);
    }

    public function testAllowsAnActionWithoutAResource(): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), 'article.create');

        self::assertNull($request->resource);
    }

    public function testAllowsZeroAsAnApplicationDefinedAction(): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), '0');

        self::assertSame('0', $request->action);
    }

    public function testRejectsAnEmptyAction(): void
    {
        try {
            new AuthorizationRequest(new Actor('42', 'user'), '');
            self::fail('Expected invalid authorization request.');
        } catch (InvalidAuthorizationRequestException $exception) {
            self::assertInstanceOf(AuthorizationException::class, $exception);
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
        }
    }
}
