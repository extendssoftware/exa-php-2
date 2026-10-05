<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Integration\Http\Factory;

use ExtendsSoftware\ExaPHP\Application\Configuration\Configuration;
use ExtendsSoftware\ExaPHP\Integration\Http\Exception\InvalidHttpConfigurationException;
use ExtendsSoftware\ExaPHP\Integration\Http\Factory\ResponseNegotiationFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseNegotiationFactoryTest extends TestCase
{
    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfigurationBeforeResolvingFactories(mixed $response): void
    {
        $services = $this->createMock(ServiceLocator::class);
        $services->expects($this->once())->method('get')->with(Configuration::class)
            ->willReturn(new Configuration(['http' => ['response' => $response]]));
        $this->expectException(InvalidHttpConfigurationException::class);
        new ResponseNegotiationFactory()->create($services);
    }

    /** @return iterable<array{mixed}> */
    public static function invalidConfiguration(): iterable
    {
        yield [null];
        yield [['factories' => null]];
        yield [['default' => null]];
        yield [['default' => '']];
        yield [['factories' => ['application/json' => '']]];
        yield [['factories' => ['application/json' => 123]]];
        yield [['factories' => ['service']]];
    }
}
