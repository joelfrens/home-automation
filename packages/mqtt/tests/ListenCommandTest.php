<?php

namespace Joelfrens\HomeAutomation\Mqtt\Tests;

use Illuminate\Support\Facades\Event;
use Joelfrens\HomeAutomation\Mqtt\Events\MessageReceived;
use Joelfrens\HomeAutomation\Mqtt\MqttConnector;
use PhpMqtt\Client\Contracts\MqttClient;

class ListenCommandTest extends TestCase
{
    /** @var array<string, callable> topic filter => callback, as registered by the command */
    private array $subscriptions = [];

    /**
     * Swap the real broker for a fake client: subscribe() remembers callbacks, loop() "receives"
     * the given messages by calling them, then returns as if Ctrl+C was pressed.
     *
     * @param  list<array{string, string}>  $messages  [topic, payload]
     */
    private function fakeBroker(array $messages): void
    {
        $client = $this->createMock(MqttClient::class);
        $client->method('subscribe')->willReturnCallback(
            function (string $filter, callable $callback) {
                $this->subscriptions[$filter] = $callback;
            }
        );
        $client->method('loop')->willReturnCallback(function () use ($messages) {
            foreach ($messages as [$topic, $payload]) {
                $callback = reset($this->subscriptions);
                $callback($topic, $payload, false);
            }
        });
        $client->expects($this->once())->method('disconnect');

        $connector = $this->createMock(MqttConnector::class);
        $connector->expects($this->once())->method('connect')->with('listen')->willReturn($client);
        $this->app->instance(MqttConnector::class, $connector);
    }

    public function test_it_subscribes_to_the_configured_topics()
    {
        config(['mqtt.subscribe' => ['home/+/+', 'garden/#']]);
        $this->fakeBroker([]);

        $this->artisan('mqtt:listen')->assertSuccessful();

        $this->assertSame(['home/+/+', 'garden/#'], array_keys($this->subscriptions));
    }

    public function test_the_topic_option_overrides_the_config()
    {
        $this->fakeBroker([]);

        $this->artisan('mqtt:listen', ['--topic' => ['test/#']])->assertSuccessful();

        $this->assertSame(['test/#'], array_keys($this->subscriptions));
    }

    public function test_every_message_becomes_a_message_received_event()
    {
        Event::fake([MessageReceived::class]);
        $this->fakeBroker([
            ['home/kitchen/bme280', '{"temperature":21}'],
            ['home/attic/bme280', '{"temperature":30}'],
        ]);

        $this->artisan('mqtt:listen')->assertSuccessful();

        Event::assertDispatchedTimes(MessageReceived::class, 2);
        Event::assertDispatched(
            MessageReceived::class,
            fn (MessageReceived $e) => $e->topic === 'home/attic/bme280' && $e->payload === '{"temperature":30}',
        );
    }

    public function test_a_failing_listener_does_not_stop_later_messages()
    {
        $seen = [];
        Event::listen(MessageReceived::class, function (MessageReceived $e) use (&$seen) {
            $seen[] = $e->topic;
            if ($e->topic === 'home/bad/bme280') {
                throw new \RuntimeException('listener blew up');
            }
        });
        $this->fakeBroker([
            ['home/bad/bme280', '{}'],
            ['home/good/bme280', '{}'],
        ]);

        $this->artisan('mqtt:listen')->assertSuccessful();

        $this->assertSame(['home/bad/bme280', 'home/good/bme280'], $seen);
    }

    public function test_the_subscribe_setting_is_read_from_a_comma_separated_env_value()
    {
        $this->assertSame(['home/+/+'], config('mqtt.subscribe'));
    }
}
