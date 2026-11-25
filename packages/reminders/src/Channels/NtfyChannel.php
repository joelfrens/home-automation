<?php

namespace Joelfrens\HomeAutomation\Reminders\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Joelfrens\HomeAutomation\Reminders\Notifications\NtfyMessage;

/**
 * Sends a notification to a phone through ntfy (https://ntfy.sh): one HTTP POST, and the ntfy
 * app on the phone shows it as a normal push notification.
 *
 * Who gets it: the notifiable's routeNotificationForNtfy() returns their topic (the "mailbox
 * name" their phone subscribes to). No topic = nothing is sent to that person.
 */
class NtfyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $topic = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('ntfy', $notification)
            : null;

        if (blank($topic) || ! method_exists($notification, 'toNtfy')) {
            return;
        }

        /** @var NtfyMessage $message */
        $message = $notification->toNtfy($notifiable);

        Http::timeout(10)
            ->when(config('reminders.ntfy.token'), fn ($http, $token) => $http->withToken($token))
            ->post(rtrim(config('reminders.ntfy.server'), '/'), ['topic' => $topic, ...$message->toArray()])
            ->throw();
    }
}
