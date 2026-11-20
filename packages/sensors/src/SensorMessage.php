<?php

namespace Joelfrens\HomeAutomation\Sensors;

use Joelfrens\HomeAutomation\Sensors\Exceptions\InvalidSensorMessage;

/**
 * Turns one MQTT message (topic + JSON payload) into a validated reading, using the same rules
 * as the Node ingestion service: topic home/<room>/<sensor>, a known sensor type, every metric
 * present as a number within its min/max, extra fields dropped.
 *
 * It only parses text, so it doesn't care how the message arrived (MQTT, HTTP, a test).
 */
final class SensorMessage
{
    /** Room and sensor names: lowercase letters, numbers and dashes, 1-40 characters. */
    private const NAME = '/^[a-z0-9-]{1,40}$/';

    public function __construct(private readonly SensorTypes $types) {}

    /**
     * @return array{room: string, sensor: string, values: array<string, float>}
     *
     * @throws InvalidSensorMessage
     */
    public function parse(string $topic, string $payload): array
    {
        $parts = explode('/', $topic);

        if (count($parts) !== 3 || $parts[0] !== 'home'
            || ! preg_match(self::NAME, $parts[1]) || ! preg_match(self::NAME, $parts[2])) {
            throw new InvalidSensorMessage("unexpected topic \"{$topic}\"");
        }

        [, $room, $sensor] = $parts;

        if (! $this->types->has($sensor)) {
            throw new InvalidSensorMessage("unknown sensor type \"{$sensor}\"");
        }

        $json = json_decode($payload, true);

        // A JSON object decodes to an associative array; [1,2,3] would be a list.
        if (! is_array($json) || array_is_list($json) && $json !== []) {
            throw new InvalidSensorMessage('payload is not a JSON object');
        }

        $values = [];

        foreach ($this->types->all()[$sensor]['metrics'] as $metric => $definition) {
            $value = $json[$metric] ?? null;

            // is_int/is_float: "22.7" (text) and true are rejected, like the Zod schema does.
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidSensorMessage("{$metric}: expected a number");
            }

            if ($value < $definition['min'] || $value > $definition['max']) {
                throw new InvalidSensorMessage(
                    "{$metric}: {$value} is outside {$definition['min']}..{$definition['max']}"
                );
            }

            $values[$metric] = (float) $value;
        }

        return ['room' => $room, 'sensor' => $sensor, 'values' => $values];
    }
}
