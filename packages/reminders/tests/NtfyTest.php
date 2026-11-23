<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Joelfrens\HomeAutomation\Reminders\EventPlanner;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;
use Joelfrens\HomeAutomation\Reminders\Notifications\NtfyTest as NtfyTestNotification;
use Joelfrens\HomeAutomation\Reminders\Notifications\ReminderDue;
use Joelfrens\HomeAutomation\Reminders\Tests\Fixtures\User;

class NtfyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['ntfy.example' => Http::response(['id' => 'x'])]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 19:00', 'UTC'));
    }

    private function reminderFor(array $event, int $daysBefore): Reminder
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'UTC'));
        $created = $this->app->make(EventPlanner::class)->create([
            ...$event, 'reminders' => [['days_before' => $daysBefore, 'time' => '19:00']],
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 19:00', 'UTC'));

        return $created->reminders()->sole();
    }

    public function test_a_reminder_is_posted_to_the_users_topic()
    {
        config()->set('reminders.event_url', 'http://home.test/school?event={id}');
        $emma = Child::create(['name' => 'Emma']);
        $reminder = $this->reminderFor([
            'title' => 'Science museum trip', 'child_id' => $emma->id, 'date' => '2026-11-24',
            'start_time' => '08:45', 'location' => 'Museum', 'needs' => 'Packed lunch, £5',
        ], 1);
        $user = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'k3J9xQ2mP8vT']);

        $user->notifyNow(new ReminderDue($reminder));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ntfy.example'
            && $request['topic'] === 'k3J9xQ2mP8vT'
            && $request['title'] === 'Emma: Science museum trip'
            && $request['message'] === "Tomorrow 08:45 · Museum\nBring: Packed lunch, £5"
            && $request['priority'] === 3
            && $request['click'] === "http://home.test/school?event={$reminder->event_id}");
    }

    public function test_same_day_reminders_are_louder()
    {
        $reminder = $this->reminderFor(['title' => 'Mufti day', 'date' => '2026-11-24'], 0);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:30', 'UTC'));
        $user = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);

        $user->notifyNow(new ReminderDue($reminder));

        Http::assertSent(fn (Request $request) => $request['priority'] === 4 && $request['message'] === 'Today'
            && $request['title'] === 'Mufti day');
    }

    public function test_nothing_is_sent_to_someone_without_a_topic()
    {
        $user = User::create(['name' => 'Joel', 'email' => 'joel@example.com']);

        $user->notifyNow(new NtfyTestNotification);

        Http::assertNothingSent();
    }

    public function test_an_access_token_is_sent_when_configured()
    {
        config()->set('reminders.ntfy.token', 'tk_secret');
        $user = User::create(['name' => 'Joel', 'email' => 'joel@example.com', 'ntfy_topic' => 'abc']);

        $user->notifyNow(new NtfyTestNotification);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tk_secret'));
    }
}
