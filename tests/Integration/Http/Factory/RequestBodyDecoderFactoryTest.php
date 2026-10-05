<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\RequestBodyDecoderFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestBodyDecoderFactoryTest extends TestCase
{
    /** @param array<string, mixed> $request */
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(array $request, string $method): void
    {
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with(Configuration::class)
            ->willReturn(new Configuration(['http' => ['request' => $request]]));
        $this->expectException(InvalidHttpConfigurationException::class);
        new RequestBodyDecoderFactory()->$method($services);
    }

    /** @return iterable<array{array<string, mixed>, string}> */
    public static function invalidConfiguration(): iterable
    {
        yield [['decoders' => null], 'create'];
        yield [['decoders' => ['application/json' => '']], 'create'];
        yield [['json' => null], 'createJson'];
        yield [['json' => ['maxBytes' => -1]], 'createJson'];
        yield [['json' => ['maxBytes' => '10']], 'createJson'];
        yield [['json' => ['maxBytes' => null]], 'createJson'];
    }
}
