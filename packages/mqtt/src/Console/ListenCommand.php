<?php

namespace Joelfrens\HomeAutomation\Mqtt\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Joelfrens\HomeAutomation\Mqtt\Events\MessageReceived;
use Joelfrens\HomeAutomation\Mqtt\MqttConnector;
use Throwable;

/**
 * A long-running process: subscribe to the configured topics and fire a MessageReceived
 * event for every message. Runs in its own container (see docker-compose.yml).
 */
class ListenCommand extends Command
{
    protected $signature = 'mqtt:listen {--topic=* : Topic(s) to subscribe to (default: config mqtt.subscribe)}';

    protected $description = 'Subscribe to MQTT topics and turn each message into a Laravel event';

    public function handle(MqttConnector $connector, Dispatcher $events): int
    {
        $topics = $this->option('topic') ?: config('mqtt.subscribe');

        $client = $connector->connect('listen');
        $this->components->info(sprintf('Connected to %s:%d', config('mqtt.host'), config('mqtt.port')));

        foreach ($topics as $topic) {
            $client->subscribe($topic, function (string $topic, string $message, bool $retained) use ($events) {
                $this->line("<fg=gray>{$topic}</> {$message}", verbosity: 'v');

                // One bad listener must not stop the loop (and with it, every later message).
                try {
                    $events->dispatch(new MessageReceived($topic, $message, $retained));
                } catch (Throwable $e) {
                    report($e);
                }
            }, 0);

            $this->components->info("Subscribed to {$topic}");
        }

        // Stop cleanly on Ctrl+C or "docker compose stop" (SIGINT/SIGTERM).
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $client->interrupt());
            pcntl_signal(SIGTERM, fn () => $client->interrupt());
        }

        $client->loop(true); // blocks here, handling messages, until interrupted
        $client->disconnect();
        $this->components->info('Disconnected');

        return self::SUCCESS;
    }
}
