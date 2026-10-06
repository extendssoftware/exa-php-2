<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Clock\Clock;
use ExtendsSoftware\ExaPHP\Clock\SystemClock;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;

return [
    'services' => [
        Clock::class => new FactoryDefinition(static fn(): Clock => new SystemClock()),
    ],
];
