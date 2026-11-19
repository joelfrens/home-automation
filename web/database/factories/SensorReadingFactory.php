<?php

namespace Database\Factories;

use App\Models\SensorReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake readings for tests.
 *
 * @extends Factory<SensorReading>
 */
class SensorReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room' => 'living-room',
            'sensor' => 'bme280',
            'temperature_c' => fake()->randomFloat(2, 18, 24),
            'humidity_pct' => fake()->randomFloat(2, 35, 60),
            'pressure_hpa' => fake()->randomFloat(2, 990, 1020),
            'recorded_at' => now(),
        ];
    }
}
