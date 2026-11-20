<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new sensor reading, pushed to every open dashboard through Reverb.
 *
 * ShouldBroadcastNow = send it to Reverb immediately (ShouldBroadcast would wait for a queue worker).
 * The browser receives the public properties below as JSON, on the "readings" channel,
 * as the event "ReadingReceived" (useEcho in resources/js/pages/dashboard.tsx).
 */
class ReadingReceived implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  string  $id  Same format as SensorReadings::recent() ids: "room|sensor|time"
     * @param  array<string, float>  $values  metric => value
     */
    public function __construct(
        public readonly string $id,
        public readonly string $room,
        public readonly string $sensor,
        public readonly string $recordedAt,
        public readonly array $values,
    ) {}

    /**
     * Public channel: anyone who can reach Reverb may listen. Fine on the home LAN for now;
     * a private channel (only logged-in users) comes next.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('readings');
    }
}
