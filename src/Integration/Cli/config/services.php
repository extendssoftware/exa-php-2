<?php

declare(strict_types=1);

use ExtendsSoftware\ExaPHP\Cli\Handler\HandlerResolver;
use ExtendsSoftware\ExaPHP\Cli\Help\HelpRenderer;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\ArgvInputParser;
use ExtendsSoftware\ExaPHP\Cli\Input\Parser\InputParser;
use ExtendsSoftware\ExaPHP\Cli\Output\Output;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandDispatcher;
use ExtendsSoftware\ExaPHP\Cli\Routing\CommandRegistry;
use ExtendsSoftware\ExaPHP\Integration\Cli\Factory\CommandDispatcherFactory;
use ExtendsSoftware\ExaPHP\Integration\Cli\Factory\CommandRegistryFactory;
use ExtendsSoftware\ExaPHP\Integration\Cli\Factory\HandlerResolverFactory;
use ExtendsSoftware\ExaPHP\Integration\Cli\Factory\OutputFactory;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\FactoryDefinition;
use ExtendsSoftware\ExaPHP\ServiceLocator\Definition\InvokableDefinition;

return [
    'services' => [
        HelpRenderer::class => new InvokableDefinition(HelpRenderer::class),
        CommandRegistry::class => new FactoryDefinition(new CommandRegistryFactory()->create(...)),
        InputParser::class => new InvokableDefinition(ArgvInputParser::class),
        HandlerResolver::class => new FactoryDefinition(new HandlerResolverFactory()->create(...)),
        CommandDispatcher::class => new FactoryDefinition(new CommandDispatcherFactory()->create(...)),
        Output::class => new FactoryDefinition(new OutputFactory()->create(...)),
    ],
];
