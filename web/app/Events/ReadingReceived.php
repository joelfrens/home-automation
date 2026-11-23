<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new sensor reading, pushed to every open dashboard through Reverb.
 *
 * ShouldBroadcastNow = send it to Reverb immediately (ShouldBroadcast would wait for a queue worker).
 * The browser receives the public properties below as JSON, on the private "readings" channel,
 * as the event "ReadingReceived" (useEcho in resources/js/hooks/use-live-readings.ts).
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
     * Private channel: before Reverb lets a browser listen, the browser must ask Laravel
     * (POST /broadcasting/auth) and Laravel only says yes to logged-in users (routes/channels.php).
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('readings');
    }
}
