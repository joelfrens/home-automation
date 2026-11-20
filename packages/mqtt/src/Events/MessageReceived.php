<?php

namespace Joelfrens\HomeAutomation\Mqtt\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by "php artisan mqtt:listen" for every MQTT message. The package doesn't know what
 * messages mean; the app (or another package) listens for this event and decides.
 */
final class MessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $topic,
        public readonly string $payload,
        public readonly bool $retained = false,
    ) {}
}
