<?php

namespace Joelfrens\HomeAutomation\Reminders\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Joelfrens\HomeAutomation\Reminders\Channels\NtfyChannel;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;

/**
 * "Emma: Science museum trip – Tomorrow 08:45. Bring: packed lunch, £5"
 *
 * Queued, so a slow or unreachable ntfy server never holds up the scheduler.
 */
class ReminderDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reminder $reminder) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        $event = $this->reminder->event;
        $timezone = config('reminders.timezone');
        $start = $event->starts_at->setTimezone($timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $day = match ($today->diffInDays($start->startOfDay())) {
            0.0 => 'Today',
            1.0 => 'Tomorrow',
            default => $start->format('l j M'),
        };
        $when = $event->all_day ? $day : "{$day} {$start->format('H:i')}";

        $lines = array_filter([
            $when.($event->location ? " · {$event->location}" : ''),
            $event->needs ? "Bring: {$event->needs}" : null,
            $event->notes,
        ]);

        $url = config('reminders.event_url');

        return new NtfyMessage(
            title: $event->child ? "{$event->child->name}: {$event->title}" : $event->title,
            message: implode("\n", $lines),
            priority: $this->reminder->days_before === 0 ? 4 : 3,
            tags: ['school'],
            click: $url ? str_replace('{id}', (string) $event->id, $url) : null,
        );
    }
}
