<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use InvalidArgumentException;
use Joelfrens\HomeAutomation\Reminders\ReminderTime;
use PHPUnit\Framework\TestCase as PlainTestCase;

class ReminderTimeTest extends PlainTestCase
{
    public function test_winter_evening_before_is_the_same_hour_in_utc()
    {
        // December: UK is on GMT (UTC+0)
        $at = ReminderTime::for('2026-12-04', 1, '19:00', 'Europe/London');

        $this->assertSame('2026-12-03T19:00:00+00:00', $at->toIso8601String());
    }

    public function test_summer_evening_before_is_one_hour_earlier_in_utc()
    {
        // June: UK is on BST (UTC+1)
        $at = ReminderTime::for('2026-06-12', 1, '19:00', 'Europe/London');

        $this->assertSame('2026-06-11T18:00:00+00:00', $at->toIso8601String());
    }

    public function test_a_week_before_across_the_clock_change_keeps_the_local_time()
    {
        // Event on Tue 3 Nov 2026 (GMT); 7 days before = Tue 27 Oct, already GMT (clocks went back 25 Oct)
        $this->assertSame('2026-10-27T19:00:00+00:00', ReminderTime::for('2026-11-03', 7, '19:00', 'Europe/London')->toIso8601String());

        // Event on Fri 30 Oct 2026 (GMT); 7 days before = Fri 23 Oct, still BST
        $this->assertSame('2026-10-23T18:00:00+00:00', ReminderTime::for('2026-10-30', 7, '19:00', 'Europe/London')->toIso8601String());
    }

    public function test_same_day_morning()
    {
        $this->assertSame('2026-12-04T07:30:00+00:00', ReminderTime::for('2026-12-04', 0, '07:30', 'Europe/London')->toIso8601String());
    }

    public function test_an_invalid_time_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);

        ReminderTime::for('2026-12-04', 1, '7pm', 'Europe/London');
    }
}
