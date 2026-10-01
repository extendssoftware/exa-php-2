<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Application\Configuration;

use ExtendsSoftware\ExaPHP\Application\Exception\DuplicateServiceDefinitionException;
use ExtendsSoftware\ExaPHP\Application\Exception\InvalidConfigurationException;

use function array_is_list;
use function array_key_exists;
use function array_replace;
use function is_array;
use function sprintf;

/**
 * Merges module defaults and application overrides into configuration.
 *
 * Associative sections merge recursively; lists and other values are replaced. The root services section merges
 * by identifier, replacing each definition as a whole. Service identifiers must be unique across modules.
 */
final readonly class ConfigurationMerger
{
    /**
     * Merges modules first, followed by application sources, preserving each collection's iteration order.
     *
     * Application sources may add or replace services but cannot suppress conflicts between modules.
     * Service definition values are preserved without validation or execution.
     *
     * @param array<string, array<array-key, mixed>> $moduleConfigurations Configuration indexed by module name.
     * @param array<string, array<array-key, mixed>> $applicationConfigurations Overrides indexed by source name.
     *
     * @return Configuration The merged configuration.
     *
     * @throws DuplicateServiceDefinitionException When two modules define the same service identifier.
     * @throws InvalidConfigurationException When a source's services section is not an array.
     */
    public function merge(array $moduleConfigurations, array $applicationConfigurations = []): Configuration
    {
        $values = [];
        $serviceOwners = [];

        foreach ($moduleConfigurations as $module => $configuration) {
            $services = $this->services($configuration, $module);
            foreach ($services as $id => $definition) {
                if (array_key_exists($id, $serviceOwners)) {
                    throw new DuplicateServiceDefinitionException(
                        sprintf('Service "%s" is defined by modules "%s" and "%s".', $id, $serviceOwners[$id], $module),
                    );
                }

                $serviceOwners[$id] = $module;
            }

            $values = $this->mergeSource($values, $configuration, $services);
        }

        foreach ($applicationConfigurations as $source => $configuration) {
            $services = $this->services($configuration, $source);
            $values = $this->mergeSource($values, $configuration, $services);
        }

        return new Configuration($values);
    }

    /**
     * Combines ordered sources belonging to one module or application override collection.
     *
     * Later sources may replace service definitions within this collection. Use merge() to detect conflicts
     * between independently combined modules.
     *
     * @param array<string, array<array-key, mixed>> $configurations Configuration indexed by source name.
     *
     * @return array<array-key, mixed> The combined configuration values.
     *
     * @throws InvalidConfigurationException When a source's services section is not an array.
     */
    public function mergeSources(array $configurations): array
    {
        $values = [];
        foreach ($configurations as $source => $configuration) {
            $values = $this->mergeSource($values, $configuration, $this->services($configuration, $source));
        }

        return $values;
    }

    /**
     * Reads a source's service map and validates its structure.
     *
     * @param array<array-key, mixed> $configuration The source configuration.
     * @param string $source The module or application source name.
     *
     * @return array<array-key, mixed> The service definitions, or an empty array when omitted.
     *
     * @throws InvalidConfigurationException When the services section is not an array.
     */
    private function services(array $configuration, string $source): array
    {
        if (!array_key_exists('services', $configuration)) {
            return [];
        }

        if (!is_array($configuration['services'])) {
            throw new InvalidConfigurationException(
                sprintf('Configuration source "%s" must define "services" as an array.', $source),
            );
        }

        return $configuration['services'];
    }

    /**
     * Merges a source while treating root service definitions as indivisible values.
     *
     * @param array<array-key, mixed> $values The accumulated configuration.
     * @param array<array-key, mixed> $configuration The incoming configuration.
     * @param array<array-key, mixed> $services The incoming service definitions.
     *
     * @return array<array-key, mixed> The merged values.
     */
    private function mergeSource(array $values, array $configuration, array $services): array
    {
        if (array_key_exists('services', $configuration)) {
            $values['services'] = array_replace($values['services'] ?? [], $services);
            unset($configuration['services']);
        }

        return $this->mergeSections($values, $configuration);
    }

    /**
     * Merges section entries, recursing only when both values are associative arrays.
     *
     * @param array<array-key, mixed> $values The accumulated section.
     * @param array<array-key, mixed> $overrides The incoming section.
     *
     * @return array<array-key, mixed> The merged section.
     */
    private function mergeSections(array $values, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $previous = $values[$key] ?? null;
            if (is_array($previous) && is_array($value) && !array_is_list($previous) && !array_is_list($value)) {
                $values[$key] = $this->mergeSections($previous, $value);
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
