<?php

namespace Joelfrens\HomeAutomation\Sensors\Tests;

use Joelfrens\HomeAutomation\Sensors\Range;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as PlainTestCase;

class RangeTest extends PlainTestCase
{
    public function test_known_values_are_parsed()
    {
        $this->assertSame(Range::SixHours, Range::fromQuery('6h'));
        $this->assertSame(Range::Day, Range::fromQuery('24h'));
        $this->assertSame(Range::Week, Range::fromQuery('7d'));
    }

    public static function oddInput(): array
    {
        return [
            'missing' => [null],
            'unknown' => ['forever'],
            'empty' => [''],
            'array (?range[]=6h)' => [['6h']],
            'number' => [24],
        ];
    }

    #[DataProvider('oddInput')]
    public function test_anything_else_falls_back_to_24_hours(mixed $value)
    {
        $this->assertSame(Range::Day, Range::fromQuery($value));
    }

    public function test_every_range_gives_a_chart_friendly_number_of_points()
    {
        foreach (Range::cases() as $range) {
            $points = $range->hours() * 3600 / $range->bucketSeconds();

            $this->assertGreaterThanOrEqual(150, $points, $range->value);
            $this->assertLessThanOrEqual(400, $points, $range->value);
        }
    }
}
