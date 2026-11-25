<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use Carbon\CarbonImmutable;
use Joelfrens\HomeAutomation\Reminders\EventPlanner;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Joelfrens\HomeAutomation\Reminders\Models\Event;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;

class EventPlannerTest extends TestCase
{
    private function planner(): EventPlanner
    {
        return $this->app->make(EventPlanner::class);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-23 12:00', 'UTC');
    }

    public function test_an_event_is_stored_in_utc_with_its_reminders()
    {
        $emma = Child::create(['name' => 'Emma']);

        $event = $this->planner()->create([
            'title' => 'Science museum trip',
            'child_id' => $emma->id,
            'date' => '2026-12-04',
            'start_time' => '08:45',
            'end_time' => '15:00',
            'needs' => 'Packed lunch, £5',
            'reminders' => [
                ['days_before' => 7, 'time' => '19:00'],
                ['days_before' => 1, 'time' => '19:00'],
            ],
        ], now: $this->now());

        $this->assertSame('2026-12-04T08:45:00+00:00', $event->starts_at->toIso8601String());
        $this->assertSame('2026-12-04T15:00:00+00:00', $event->ends_at->toIso8601String());
        $this->assertFalse($event->all_day);
        $this->assertSame(
            ['2026-11-27T19:00:00+00:00', '2026-12-03T19:00:00+00:00'],
            $event->reminders->map(fn (Reminder $r) => $r->remind_at->toIso8601String())->all(),
        );
    }

    public function test_an_event_without_a_start_time_is_all_day()
    {
        $event = $this->planner()->create(['title' => 'Christmas jumper day', 'date' => '2026-12-11'], now: $this->now());

        $this->assertTrue($event->all_day);
        $this->assertSame('2026-12-11T00:00:00+00:00', $event->starts_at->toIso8601String());
        $this->assertNull($event->ends_at);
    }

    public function test_summer_times_are_converted_from_british_summer_time()
    {
        $event = $this->planner()->create(['title' => 'Sports day', 'date' => '2027-06-10', 'start_time' => '13:30'], now: $this->now());

        $this->assertSame('2027-06-10T12:30:00+00:00', $event->starts_at->toIso8601String());
    }

    public function test_reminders_that_are_already_in_the_past_are_not_created()
    {
        $event = $this->planner()->create([
            'title' => 'Cake sale',
            'date' => '2026-11-25',
            'reminders' => [
                ['days_before' => 7, 'time' => '19:00'],  // 18 Nov: past
                ['days_before' => 1, 'time' => '19:00'],  // 24 Nov: future
                ['days_before' => 1, 'time' => '19:00'],  // duplicate
            ],
        ], now: $this->now());

        $this->assertCount(1, $event->reminders);
        $this->assertSame(1, $event->reminders->first()->days_before);
    }

    public function test_updating_replaces_unsent_reminders_but_keeps_sent_ones()
    {
        $event = $this->planner()->create([
            'title' => 'Trip',
            'date' => '2026-12-04',
            'start_time' => '09:00',
            'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']],
        ], now: $this->now());

        $event->reminders()->where('days_before', 7)->update(['sent_at' => $this->now()]);

        $this->planner()->update($event, [
            'title' => 'Museum trip',
            'date' => '2026-12-04',
            'start_time' => '09:30',
            'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 0, 'time' => '07:30']],
        ], now: $this->now());

        $event->refresh();
        $this->assertSame('Museum trip', $event->title);
        $this->assertSame('2026-12-04T09:30:00+00:00', $event->starts_at->toIso8601String());
        $this->assertEqualsCanonicalizing(
            ['7|19:00|sent', '0|07:30|pending'],
            Reminder::all()->map(fn (Reminder $r) => "{$r->days_before}|{$r->time}|".($r->sent_at ? 'sent' : 'pending'))->all(),
        );
    }

    public function test_upcoming_starts_at_the_beginning_of_today_and_is_sorted()
    {
        $planner = $this->planner();
        $planner->create(['title' => 'Yesterday', 'date' => '2026-11-22'], now: $this->now());
        $planner->create(['title' => 'Later', 'date' => '2026-12-01'], now: $this->now());
        $planner->create(['title' => 'This morning', 'date' => '2026-11-23', 'start_time' => '08:00'], now: $this->now());

        $this->assertSame(
            ['This morning', 'Later'],
            $planner->upcoming(now: $this->now())->pluck('title')->all(),
        );
        $this->assertCount(1, $planner->upcoming(limit: 1, now: $this->now()));
    }

    public function test_present_gives_local_dates_and_times()
    {
        $leo = Child::create(['name' => 'Leo', 'colour' => 'emerald']);
        $event = $this->planner()->create([
            'title' => 'Sports day', 'child_id' => $leo->id, 'date' => '2027-06-10', 'start_time' => '13:30',
            'reminders' => [['days_before' => 1, 'time' => '19:00']],
        ], now: $this->now());

        $presented = $this->planner()->present(Event::with(['child', 'reminders'])->find($event->id));

        $this->assertSame('2027-06-10', $presented['date']);
        $this->assertSame('13:30', $presented['startTime']);
        $this->assertSame(['id' => $leo->id, 'name' => 'Leo', 'colour' => 'emerald'], $presented['child']);
        $this->assertSame(1, $presented['reminders'][0]['daysBefore']);
        $this->assertNull($presented['reminders'][0]['sentAt']);
    }
}
