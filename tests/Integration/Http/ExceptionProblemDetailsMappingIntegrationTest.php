<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http;

use ExtendsSoftware\ExaPHP\Application\Configuration\ConfigurationMerger;
use ExtendsSoftware\ExaPHP\Application\Factory\ServiceLocatorFactory;
use ExtendsSoftware\ExaPHP\Http\Decoding\Exception\MalformedRequestBodyException;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ExceptionProblemDetailsMapper;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ExceptionResponseFactory;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\Message\Method;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Message\Uri;
use ExtendsSoftware\ExaPHP\Integration\Http\HttpModule;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InstanceDefinition;
use PHPUnit\Framework\TestCase;
use TypeError;

final class ExceptionProblemDetailsMappingIntegrationTest extends TestCase
{
    public function testApplicationOverrideRetainsModuleOrderAndDefaultFallback(): void
    {
        $calls = [];
        $article = $this->createStub(ExceptionProblemDetailsMapper::class);
        $article->method('map')->willReturnCallback(static function () use (&$calls): ?ProblemDetails {
            $calls[] = 'article';

            return null;
        });
        $account = $this->createStub(ExceptionProblemDetailsMapper::class);
        $account->method('map')->willReturnCallback(static function () use (&$calls): ?ProblemDetails {
            $calls[] = 'account';

            return null;
        });
        $defaults = require new HttpModule()->configDirectory() . '/services.php';
        $configuration = new ConfigurationMerger()->merge([
            'http' => $defaults,
            'article' => ['http' => ['exceptionMappers' => ['article' => 'missing-overridden-service']]],
            'account' => ['http' => ['exceptionMappers' => ['account' => 'account']]],
        ], ['application' => [
            'http' => ['exceptionMappers' => ['article' => 'article']],
            'services' => [
                'article' => new InstanceDefinition($article),
                'account' => new InstanceDefinition($account),
            ],
        ]]);
        $services = new ServiceLocatorFactory()->create($configuration);
        $factory = $services->get(ExceptionResponseFactory::class);
        $request = new Request(Method::Get, new Uri('/'));
        $this->assertSame(StatusCode::BadRequest, $factory->create(new MalformedRequestBodyException(), $request)->statusCode);
        $this->assertSame(['article', 'account'], $calls);
        $this->assertSame(StatusCode::InternalServerError, $factory->create(new TypeError(), $request)->statusCode);
        $this->assertSame($factory, $services->get(ExceptionResponseFactory::class));
    }
}
