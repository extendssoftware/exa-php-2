<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Integration\Cli\Factory;

use ExtendsSoftware\ExaPHP\Cli\Output\Exception\InvalidOutputStreamException;
use ExtendsSoftware\ExaPHP\Cli\Output\StreamOutput;
use ExtendsSoftware\ExaPHP\Integration\Cli\Exception\InvalidCliConfigurationException;
use ExtendsSoftware\ExaPHP\ServiceLocator\ServiceLocator;

use function constant;
use function defined;

/**
 * Creates output using the PHP CLI runtime's standard streams.
 */
final readonly class OutputFactory
{
    /**
     * Borrows STDOUT and STDERR without opening or closing streams.
     *
     * @param ServiceLocator $serviceLocator The locator supplied by the factory resolver.
     *
     * @return StreamOutput The standard output channels.
     *
     * @throws InvalidCliConfigurationException When PHP does not provide standard CLI streams.
     * @throws InvalidOutputStreamException When the runtime streams are not writable.
     */
    public function create(ServiceLocator $serviceLocator): StreamOutput
    {
        if (!defined('STDOUT') || !defined('STDERR')) {
            throw new InvalidCliConfigurationException('CLI output requires the STDOUT and STDERR runtime streams.');
        }

        return new StreamOutput(constant('STDOUT'), constant('STDERR'));
    }
}
