<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Authorization;

use ExtendsSoftware\ExaPHP\Authorization\AuthorizationException;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationGuard;
use ExtendsSoftware\ExaPHP\Authorization\AuthorizationRequest;
use ExtendsSoftware\ExaPHP\Authorization\Authorizer;
use ExtendsSoftware\ExaPHP\Authorization\Exception\AccessDeniedException;
use ExtendsSoftware\ExaPHP\Identity\Actor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class AuthorizationGuardTest extends TestCase
{
    public function testReturnsNormallyWhenTheExactRequestIsGranted(): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), 'article.create');
        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->expects($this->once())->method('isGranted')->with($this->identicalTo($request))->willReturn(true);

        new AuthorizationGuard($authorizer)->assertGranted($request);
    }

    public function testThrowsAccessDeniedAndPreservesTheDeniedRequest(): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), 'article.create');
        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->expects($this->once())->method('isGranted')->with($this->identicalTo($request))->willReturn(false);

        try {
            new AuthorizationGuard($authorizer)->assertGranted($request);
            self::fail('Expected access denial.');
        } catch (AccessDeniedException $exception) {
            self::assertSame($request, $exception->request);
            self::assertInstanceOf(AuthorizationException::class, $exception);
            self::assertInstanceOf(RuntimeException::class, $exception);
        }
    }

    #[DataProvider('evaluationFailures')]
    public function testPropagatesEvaluationFailuresUnchanged(Throwable $failure): void
    {
        $request = new AuthorizationRequest(new Actor('42', 'user'), 'article.create');
        $authorizer = $this->createMock(Authorizer::class);
        $authorizer->expects($this->once())->method('isGranted')->with($this->identicalTo($request))
            ->willThrowException($failure);

        try {
            new AuthorizationGuard($authorizer)->assertGranted($request);
            self::fail('Expected evaluation failure.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /** @return iterable<string, array{Throwable}> */
    public static function evaluationFailures(): iterable
    {
        yield 'exception' => [new RuntimeException('Permission evaluation failed.')];
        yield 'engine error' => [new TypeError('Invalid policy result.')];
    }
}
