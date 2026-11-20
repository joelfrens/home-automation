<?php

namespace Joelfrens\HomeAutomation\Sensors\Tests;

use InvalidArgumentException;
use JsonException;
use Joelfrens\HomeAutomation\Sensors\SensorTypes;
use PHPUnit\Framework\TestCase as PlainTestCase;

class SensorTypesTest extends PlainTestCase
{
    private function shippedTypes(): SensorTypes
    {
        return SensorTypes::fromFile(__DIR__.'/../sensor-types.json');
    }

    private function fromJson(string $json): SensorTypes
    {
        $path = tempnam(sys_get_temp_dir(), 'types');
        file_put_contents($path, $json);

        try {
            return SensorTypes::fromFile($path);
        } finally {
            unlink($path);
        }
    }

    public function test_the_shipped_file_defines_the_bme280()
    {
        $types = $this->shippedTypes();

        $this->assertTrue($types->has('bme280'));
        $this->assertSame(
            ['label' => 'Humidity', 'unit' => '%', 'min' => 0, 'max' => 100],
            $types->metric('bme280', 'humidity'),
        );
    }

    public function test_unknown_sensors_and_metrics_return_nothing()
    {
        $types = $this->shippedTypes();

        $this->assertFalse($types->has('water-level'));
        $this->assertNull($types->metric('bme280', 'co2'));
        $this->assertNull($types->metric('water-level', 'level'));
    }

    public function test_metrics_lists_each_metric_once_across_types()
    {
        $types = $this->fromJson(json_encode([
            'bme280' => ['label' => 'BME280', 'metrics' => [
                'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'min' => -40, 'max' => 85],
                'humidity' => ['label' => 'Humidity', 'unit' => '%', 'min' => 0, 'max' => 100],
            ]],
            'scd40' => ['label' => 'SCD40', 'metrics' => [
                'co2' => ['label' => 'CO₂', 'unit' => 'ppm', 'min' => 0, 'max' => 40000],
                'temperature' => ['label' => 'Temp (SCD40)', 'unit' => '°C', 'min' => -10, 'max' => 60],
            ]],
        ]));

        $this->assertSame(
            [
                'temperature' => ['label' => 'Temperature', 'unit' => '°C'], // first definition wins
                'humidity' => ['label' => 'Humidity', 'unit' => '%'],
                'co2' => ['label' => 'CO₂', 'unit' => 'ppm'],
            ],
            $types->metrics(),
        );
        $this->assertSame(2, $types->maxMetricsPerMessage());
    }

    public function test_a_missing_file_is_reported_clearly()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        SensorTypes::fromFile('/nope/sensor-types.json');
    }

    public function test_broken_json_is_rejected()
    {
        $this->expectException(JsonException::class);

        $this->fromJson('{ "bme280": ');
    }

    public function test_a_metric_without_a_unit_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bme280.temperature');

        $this->fromJson('{"bme280":{"label":"BME280","metrics":{"temperature":{"label":"T","min":0,"max":1}}}}');
    }

    public function test_a_type_without_metrics_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no metrics');

        $this->fromJson('{"bme280":{"label":"BME280","metrics":{}}}');
    }
}
