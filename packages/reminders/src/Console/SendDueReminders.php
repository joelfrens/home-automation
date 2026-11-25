<?php

namespace Joelfrens\HomeAutomation\Reminders\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;
use Joelfrens\HomeAutomation\Reminders\Notifications\ReminderDue;

/**
 * Runs every minute (the scheduler container): finds reminders whose moment has come and
 * pushes them to every family member who set up ntfy.
 *
 * Never twice: each reminder is "claimed" (sent_at set, only if still empty) before it is sent.
 * Too late: if the event is already over (the server was down), it is skipped, not sent.
 */
class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Push reminders that are due to family members\' phones (ntfy)';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $userModel = config('auth.providers.users.model');
        $sent = 0;

        Reminder::query()
            ->pending()
            ->where('remind_at', '<=', $now)
            ->with('event.child')
            ->orderBy('remind_at')
            ->each(function (Reminder $reminder) use ($now, $userModel, &$sent) {
                if ($reminder->event->isOver($now)) {
                    $reminder->update(['skipped_at' => $now]);
                    $this->line("Skipped (event already started): {$reminder->event->title}");

                    return;
                }

                $claimed = Reminder::query()->whereKey($reminder->id)->whereNull('sent_at')->update(['sent_at' => $now]);

                if ($claimed === 0) {
                    return; // another run got there first
                }

                Notification::send($userModel::all(), new ReminderDue($reminder));
                $sent++;
                $this->line("Sent: {$reminder->event->title}");
            });

        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
