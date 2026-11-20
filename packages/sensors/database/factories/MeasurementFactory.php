<?php

namespace Joelfrens\HomeAutomation\Sensors\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Joelfrens\HomeAutomation\Sensors\Models\Measurement;

/**
 * Fake measurements for tests (packages and the host app).
 *
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    protected $model = Measurement::class;

    public function definition(): array
    {
        return [
            'recorded_at' => now(),
            'room' => 'living-room',
            'sensor' => 'bme280',
            'metric' => 'temperature',
            'value' => fake()->randomFloat(2, 18, 24),
            'unit' => '°C',
        ];
    }

    /**
     * One whole BME280 message (three rows sharing recorded_at), e.g.
     * MeasurementFactory::message('kitchen', now(), ['temperature' => 21, 'humidity' => 45]).
     */
    public static function message(string $room, \DateTimeInterface $at, array $values, string $sensor = 'bme280'): void
    {
        $units = ['temperature' => '°C', 'humidity' => '%', 'pressure' => 'hPa'];

        foreach ($values as $metric => $value) {
            Measurement::factory()->create([
                'room' => $room,
                'sensor' => $sensor,
                'metric' => $metric,
                'value' => $value,
                'unit' => $units[$metric] ?? '',
                'recorded_at' => $at,
            ]);
        }
    }
}
