<?php

namespace Joelfrens\HomeAutomation\Sensors\Tests;

use Illuminate\Support\Facades\Schema;
use Joelfrens\HomeAutomation\Sensors\Database\Factories\MeasurementFactory;
use Joelfrens\HomeAutomation\Sensors\Range;
use Joelfrens\HomeAutomation\Sensors\SensorReadings;
use Joelfrens\HomeAutomation\Sensors\SensorTypes;

class SensorReadingsTest extends TestCase
{
    private SensorReadings $readings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime(); // "now" stays fixed, so time ranges are predictable
        $this->readings = $this->app->make(SensorReadings::class);
    }

    public function test_the_package_registers_its_services_and_table()
    {
        $this->assertTrue(Schema::hasTable('measurements'));
        $this->assertTrue($this->app->make(SensorTypes::class)->has('bme280'));
        $this->assertSame($this->readings, $this->app->make(SensorReadings::class)); // singleton
    }

    public function test_everything_is_empty_without_data()
    {
        $this->assertSame([], $this->readings->latestPerRoom());
        $this->assertSame([], $this->readings->history(Range::Day));
        $this->assertSame([], $this->readings->recent());
    }

    // ---------- latestPerRoom ----------

    public function test_latest_gives_the_newest_values_of_each_room_grouped_into_one_reading()
    {
        MeasurementFactory::message('kitchen', now()->subMinutes(10), ['temperature' => 18, 'humidity' => 40, 'pressure' => 1000]);
        MeasurementFactory::message('kitchen', now()->subSeconds(5), ['temperature' => 21.456, 'humidity' => 45, 'pressure' => 1001]);
        MeasurementFactory::message('bedroom', now()->subSeconds(3), ['temperature' => 19, 'humidity' => 50, 'pressure' => 1002]);

        $latest = $this->readings->latestPerRoom();

        $this->assertCount(2, $latest);
        $this->assertSame('bedroom', $latest[0]['room']); // sorted by room name
        $this->assertSame(
            [
                'room' => 'kitchen',
                'sensor' => 'bme280',
                'recordedAt' => now()->subSeconds(5)->toIso8601String(),
                'values' => ['temperature' => 21.46, 'humidity' => 45.0, 'pressure' => 1001.0],
            ],
            $latest[1],
        );
    }

    public function test_latest_ignores_sensors_silent_for_longer_than_the_lookback()
    {
        MeasurementFactory::message('attic', now()->subDays(8), ['temperature' => 10]);
        MeasurementFactory::message('kitchen', now()->subDays(6), ['temperature' => 20]);

        $this->assertSame(['kitchen'], array_column($this->readings->latestPerRoom(), 'room'));
    }

    // ---------- history ----------

    public function test_history_averages_each_metric_per_time_bucket()
    {
        // 24h range = 5-minute buckets. Two messages in the same bucket are averaged.
        $bucket = now()->subHour()->startOfHour();
        MeasurementFactory::message('kitchen', $bucket->copy()->addSeconds(10), ['temperature' => 20, 'humidity' => 40]);
        MeasurementFactory::message('kitchen', $bucket->copy()->addSeconds(200), ['temperature' => 22, 'humidity' => 50]);
        MeasurementFactory::message('kitchen', $bucket->copy()->addMinutes(10), ['temperature' => 25]);

        $t = $bucket->getTimestamp() * 1000;

        $this->assertSame(
            [
                ['room' => 'kitchen', 'metric' => 'humidity', 't' => $t, 'value' => 45.0],
                ['room' => 'kitchen', 'metric' => 'temperature', 't' => $t, 'value' => 21.0],
                ['room' => 'kitchen', 'metric' => 'temperature', 't' => $t + 600_000, 'value' => 25.0],
            ],
            $this->readings->history(Range::Day),
        );
    }

    public function test_history_keeps_rooms_apart()
    {
        $at = now()->subMinutes(30)->startOfHour();
        MeasurementFactory::message('kitchen', $at, ['temperature' => 20]);
        MeasurementFactory::message('bedroom', $at, ['temperature' => 30]);

        $this->assertSame(
            [['bedroom', 30.0], ['kitchen', 20.0]],
            array_map(fn ($p) => [$p['room'], $p['value']], $this->readings->history(Range::Day)),
        );
    }

    public function test_history_only_includes_the_selected_range()
    {
        MeasurementFactory::message('kitchen', now()->subHours(2), ['temperature' => 20]);
        MeasurementFactory::message('kitchen', now()->subHours(12), ['temperature' => 20]);
        MeasurementFactory::message('kitchen', now()->subDays(3), ['temperature' => 20]);

        $this->assertCount(1, $this->readings->history(Range::SixHours));
        $this->assertCount(2, $this->readings->history(Range::Day));
        $this->assertCount(3, $this->readings->history(Range::Week));
    }

    // ---------- recent ----------

    public function test_recent_rebuilds_messages_newest_first()
    {
        MeasurementFactory::message('kitchen', now()->subSeconds(10), ['temperature' => 20, 'humidity' => 40, 'pressure' => 1000]);
        MeasurementFactory::message('bedroom', now()->subSeconds(5), ['temperature' => 19, 'humidity' => 50, 'pressure' => 1005]);

        $recent = $this->readings->recent();

        $this->assertCount(2, $recent);
        $this->assertSame('bedroom', $recent[0]['room']);
        $this->assertSame(['temperature' => 20.0, 'humidity' => 40.0, 'pressure' => 1000.0], $recent[1]['values']);
        $this->assertNotSame($recent[0]['id'], $recent[1]['id']); // stable, unique keys for the UI
    }

    public function test_recent_returns_at_most_the_requested_number_of_messages()
    {
        for ($i = 1; $i <= 5; $i++) {
            MeasurementFactory::message('kitchen', now()->subSeconds($i * 5), ['temperature' => $i, 'humidity' => 40, 'pressure' => 1000]);
        }

        $recent = $this->readings->recent(3);

        $this->assertCount(3, $recent);
        $this->assertSame([1.0, 2.0, 3.0], array_map(fn ($r) => $r['values']['temperature'], $recent));
        foreach ($recent as $reading) {
            $this->assertCount(3, $reading['values']); // every message complete, none cut in half
        }
    }
}
