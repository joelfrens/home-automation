<?php

return [

    // The broker (Mosquitto). Inside Docker the host is the service name "mosquitto".
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 1883),

    // Optional login (Mosquitto currently allows anonymous clients on the LAN).
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    // Each connection gets "<prefix>-<purpose>-<random>" so the broker can tell them apart.
    'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'laravel'),

    // Seconds between keep-alive pings; the broker drops clients that go quiet for 1.5x this.
    'keep_alive' => (int) env('MQTT_KEEP_ALIVE', 30),

    // Topics "php artisan mqtt:listen" subscribes to (comma-separated in .env).
    // + matches one level: home/+/+ = home/<room>/<sensor>.
    'subscribe' => array_filter(array_map('trim', explode(',', (string) env('MQTT_SUBSCRIBE', 'home/+/+')))),

];
