<?php

namespace Joelfrens\HomeAutomation\Sensors;

use InvalidArgumentException;

/**
 * The registry of known sensor types and what they measure, loaded from sensor-types.json.
 * The same JSON file is read by the Node ingestion service to validate incoming MQTT messages,
 * so both sides always agree on metric names, units and valid ranges.
 *
 * Shape: { "<sensor>": { "label": "...", "metrics": { "<metric>": { label, unit, min, max } } } }
 */
final class SensorTypes
{
    /**
     * @param  array<string, array{label: string, metrics: array<string, array{label: string, unit: string, min: int|float, max: int|float}>}>  $types
     */
    public function __construct(private readonly array $types) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Sensor types file not found: {$path}");
        }

        $types = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($types as $sensor => $type) {
            if (! is_array($type['metrics'] ?? null) || $type['metrics'] === []) {
                throw new InvalidArgumentException("Sensor type [{$sensor}] has no metrics in {$path}");
            }

            foreach ($type['metrics'] as $metric => $definition) {
                foreach (['label', 'unit', 'min', 'max'] as $key) {
                    if (! array_key_exists($key, $definition)) {
                        throw new InvalidArgumentException("Metric [{$sensor}.{$metric}] is missing \"{$key}\" in {$path}");
                    }
                }
            }
        }

        return new self($types);
    }

    /** Every sensor type, keyed by name (e.g. "bme280"). */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $sensor): bool
    {
        return isset($this->types[$sensor]);
    }

    /** One metric's definition, e.g. metric('bme280', 'humidity') → [label, unit, min, max]; null if unknown. */
    public function metric(string $sensor, string $metric): ?array
    {
        return $this->types[$sensor]['metrics'][$metric] ?? null;
    }

    /**
     * Every distinct metric across all sensor types, in file order: ['temperature' => [label, unit], ...].
     * (If two sensor types both measure "temperature", the first definition wins.)
     *
     * @return array<string, array{label: string, unit: string}>
     */
    public function metrics(): array
    {
        $metrics = [];

        foreach ($this->types as $type) {
            foreach ($type['metrics'] as $key => $definition) {
                $metrics[$key] ??= ['label' => $definition['label'], 'unit' => $definition['unit']];
            }
        }

        return $metrics;
    }

    /** The largest number of metrics any one sensor type sends in a message. */
    public function maxMetricsPerMessage(): int
    {
        return max(array_map(fn (array $type) => count($type['metrics']), $this->types) ?: [1]);
    }
}
