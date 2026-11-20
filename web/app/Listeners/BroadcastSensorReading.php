<?php

namespace App\Listeners;

use App\Events\ReadingReceived;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;
use Joelfrens\HomeAutomation\Mqtt\Events\MessageReceived;
use Joelfrens\HomeAutomation\Sensors\Exceptions\InvalidSensorMessage;
use Joelfrens\HomeAutomation\Sensors\SensorMessage;

/**
 * The glue between the two packages: an MQTT message (mqtt package) that is a valid sensor
 * reading (sensors package) gets broadcast to the dashboards (Reverb).
 *
 * Saving to the database is NOT done here; the Node ingestion service does that.
 * Laravel finds this listener automatically from the type-hint on handle().
 */
class BroadcastSensorReading
{
    public function __construct(private readonly SensorMessage $sensorMessage) {}

    public function handle(MessageReceived $message): void
    {
        try {
            $reading = $this->sensorMessage->parse($message->topic, $message->payload);
        } catch (InvalidSensorMessage $e) {
            // Ingestion already logs rejected messages as warnings; keep this quiet.
            Log::debug("Not broadcasting {$message->topic}: {$e->getMessage()}");

            return;
        }

        $now = now();

        try {
            ReadingReceived::dispatch(
                "{$reading['room']}|{$reading['sensor']}|{$now->format('Y-m-d\TH:i:s.v')}",
                $reading['room'],
                $reading['sensor'],
                $now->toIso8601String(),
                $reading['values'],
            );
        } catch (BroadcastException $e) {
            // Reverb is down: the reading is still saved by ingestion, dashboards catch up on reconnect.
            Log::warning("Could not broadcast {$message->topic}: {$e->getMessage()}");
        }
    }
}
