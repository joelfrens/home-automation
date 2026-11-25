<?php

namespace Joelfrens\HomeAutomation\Reminders\Notifications;

use Illuminate\Notifications\Notification;
use Joelfrens\HomeAutomation\Reminders\Channels\NtfyChannel;

/** "Send test notification" in Settings → Profile: proves the phone is subscribed correctly. */
class NtfyTest extends Notification
{
    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return new NtfyMessage(
            title: 'Home automation',
            message: 'Notifications work! School reminders will arrive here.',
            tags: ['white_check_mark'],
        );
    }
}
