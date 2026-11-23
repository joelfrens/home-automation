<?php

return [

    /*
     * The household's time zone. Times are stored in UTC; this is used to understand
     * "19:00 the evening before" and to write dates in prompts and notifications.
     */
    'timezone' => env('HOUSEHOLD_TIMEZONE', 'Europe/London'),

    /*
     * The AI that reads screenshots, PDFs and messages. Needs vision and exact dates,
     * so it runs on Claude (laravel/ai provider name + model).
     */
    'ai' => [
        'provider' => env('REMINDERS_AI_PROVIDER', 'anthropic'),
        'model' => env('REMINDERS_AI_MODEL', 'claude-opus-5-5'),
        'timeout' => (int) env('REMINDERS_AI_TIMEOUT', 120),
    ],

    /*
     * Uploaded letters/screenshots are kept privately (never in public/) so you can look back.
     */
    'storage' => [
        'disk' => env('REMINDERS_DISK', 'local'),
        'path' => 'reminders',
    ],

    /*
     * Phone push via ntfy (https://ntfy.sh). Each user subscribes to their own random topic.
     * Optional access token for a protected or self-hosted server.
     */
    'ntfy' => [
        'server' => env('NTFY_SERVER', 'https://ntfy.sh'),
        'token' => env('NTFY_TOKEN'),
    ],

    /*
     * Where tapping a notification goes; {id} is replaced by the event id. Null = no link.
     */
    'event_url' => env('REMINDERS_EVENT_URL'),

];
