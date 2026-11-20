<?php

namespace Joelfrens\HomeAutomation\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\MqttClient;

/**
 * Opens connections to the MQTT broker using config/mqtt.php. The one place that knows
 * how to connect: the listener uses it now, the switches package will use it to publish commands.
 */
class MqttConnector
{
    /** @param  array{host: string, port: int, username: ?string, password: ?string, client_id_prefix: string, keep_alive: int}  $config */
    public function __construct(private readonly array $config) {}

    /** Connect and return a ready-to-use client (throws if the broker can't be reached). $purpose ends up in the client id, e.g. "listen". */
    public function connect(string $purpose): MqttClientContract
    {
        $clientId = sprintf('%s-%s-%s', $this->config['client_id_prefix'], $purpose, bin2hex(random_bytes(3)));

        $client = new MqttClient($this->config['host'], $this->config['port'], $clientId);

        $settings = (new ConnectionSettings)
            ->setUsername($this->config['username'] ?? null)
            ->setPassword($this->config['password'] ?? null)
            ->setKeepAliveInterval($this->config['keep_alive'])
            ->setConnectTimeout(5);

        // Clean session = the broker forgets us on disconnect; we only want live messages.
        // No automatic reconnect (php-mqtt doesn't allow it with a clean session): if the broker
        // goes away, the error ends the process and Docker restarts it (restart: unless-stopped).
        $client->connect($settings, true);

        return $client;
    }
}
