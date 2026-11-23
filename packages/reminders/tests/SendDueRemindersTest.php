<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Joelfrens\HomeAutomation\Reminders\EventPlanner;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;
use Joelfrens\HomeAutomation\Reminders\Notifications\ReminderDue;
use Joelfrens\HomeAutomation\Reminders\Tests\Fixtures\User;

class SendDueRemindersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'UTC'));
    }

    private function event(string $date, ?string $time, array $reminders): void
    {
        $this->app->make(EventPlanner::class)->create([
            'title' => 'PE day', 'date' => $date, 'start_time' => $time, 'reminders' => $reminders,
        ]);
    }

    public function test_a_due_reminder_goes_to_every_family_member_once()
    {
        $joel = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);
        $partner = User::create(['name' => 'Partner', 'email' => 'p@example.com', 'ntfy_topic' => 'def']);
        $this->event('2026-11-24', '09:00', [['days_before' => 1, 'time' => '19:00']]);

        $this->travelTo(CarbonImmutable::parse('2026-11-23 19:00:30', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentToTimes($joel, ReminderDue::class, 1);
        Notification::assertSentToTimes($partner, ReminderDue::class, 1);
        $this->assertNotNull(Reminder::sole()->sent_at);
    }

    public function test_reminders_in_the_future_wait()
    {
        User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);
        $this->event('2026-11-24', '09:00', [['days_before' => 1, 'time' => '19:00']]);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull(Reminder::sole()->sent_at);
    }

    public function test_a_missed_reminder_is_still_sent_late_if_the_event_has_not_started()
    {
        $joel = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);
        $this->event('2026-11-24', '09:00', [['days_before' => 1, 'time' => '19:00']]);

        // server was down overnight; back at 07:00 the next morning
        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:00', 'UTC'));
        $this->artisan('reminders:send');

        Notification::assertSentTo($joel, ReminderDue::class);
    }

    public function test_a_reminder_for_an_event_that_already_started_is_skipped()
    {
        $joel = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);
        $this->event('2026-11-24', '09:00', [['days_before' => 1, 'time' => '19:00']]);

        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00', 'UTC'));
        $this->artisan('reminders:send');

        Notification::assertNotSentTo($joel, ReminderDue::class);
        $this->assertNotNull(Reminder::sole()->skipped_at);
    }

    public function test_a_morning_reminder_for_an_all_day_event_is_still_sent()
    {
        $joel = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);
        $this->event('2026-11-24', null, [['days_before' => 0, 'time' => '07:30']]);

        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:31', 'UTC'));
        $this->artisan('reminders:send');

        Notification::assertSentTo($joel, ReminderDue::class);
    }
}
