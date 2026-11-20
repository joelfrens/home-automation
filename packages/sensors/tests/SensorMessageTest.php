<?php

namespace Joelfrens\HomeAutomation\Sensors\Tests;

use Joelfrens\HomeAutomation\Sensors\Exceptions\InvalidSensorMessage;
use Joelfrens\HomeAutomation\Sensors\SensorMessage;
use Joelfrens\HomeAutomation\Sensors\SensorTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as PlainTestCase;

class SensorMessageTest extends PlainTestCase
{
    private const VALID = '{"temperature":22.7,"humidity":40,"pressure":984.88}';

    private function parser(): SensorMessage
    {
        return new SensorMessage(SensorTypes::fromFile(__DIR__.'/../sensor-types.json'));
    }

    public function test_a_valid_message_becomes_a_reading()
    {
        $this->assertSame(
            ['room' => 'living-room', 'sensor' => 'bme280', 'values' => ['temperature' => 22.7, 'humidity' => 40.0, 'pressure' => 984.88]],
            $this->parser()->parse('home/living-room/bme280', self::VALID),
        );
    }

    public function test_values_exactly_on_the_limits_are_accepted()
    {
        $reading = $this->parser()->parse('home/attic/bme280', '{"temperature":-40,"humidity":100,"pressure":1100}');

        $this->assertSame(['temperature' => -40.0, 'humidity' => 100.0, 'pressure' => 1100.0], $reading['values']);
    }

    public function test_extra_fields_are_dropped()
    {
        $reading = $this->parser()->parse('home/kitchen/bme280', '{"temperature":21,"humidity":45,"pressure":1000,"battery":87}');

        $this->assertSame(['temperature', 'humidity', 'pressure'], array_keys($reading['values']));
    }

    public static function invalidMessages(): array
    {
        return [
            'wrong prefix' => ['office/kitchen/bme280', self::VALID, 'unexpected topic'],
            'too few topic parts' => ['home/kitchen', self::VALID, 'unexpected topic'],
            'too many topic parts' => ['home/kitchen/bme280/cmd', self::VALID, 'unexpected topic'],
            'uppercase room' => ['home/Kitchen/bme280', self::VALID, 'unexpected topic'],
            'room name too long' => ['home/'.str_repeat('a', 41).'/bme280', self::VALID, 'unexpected topic'],
            'unknown sensor type' => ['home/kitchen/water-level', '{"level":12}', 'unknown sensor type "water-level"'],
            'not JSON' => ['home/kitchen/bme280', 'hello', 'not a JSON object'],
            'a JSON list' => ['home/kitchen/bme280', '[22.7,40,984]', 'not a JSON object'],
            'missing metric' => ['home/kitchen/bme280', '{"temperature":22.7,"humidity":40}', 'pressure: expected a number'],
            'number sent as text' => ['home/kitchen/bme280', '{"temperature":"22.7","humidity":40,"pressure":1000}', 'temperature: expected a number'],
            'null value' => ['home/kitchen/bme280', '{"temperature":22.7,"humidity":null,"pressure":1000}', 'humidity: expected a number'],
            'humidity above 100' => ['home/kitchen/bme280', '{"temperature":22.7,"humidity":150,"pressure":1000}', 'humidity: 150 is outside 0..100'],
            'temperature too low' => ['home/kitchen/bme280', '{"temperature":-40.1,"humidity":40,"pressure":1000}', 'temperature:'],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_invalid_messages_are_rejected_with_a_reason(string $topic, string $payload, string $reason)
    {
        $this->expectException(InvalidSensorMessage::class);
        $this->expectExceptionMessage($reason);

        $this->parser()->parse($topic, $payload);
    }
}
