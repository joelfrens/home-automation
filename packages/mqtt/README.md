# joelfrens/home-automation-mqtt

Laravel package with the shared connection to the MQTT broker (Mosquitto). It knows nothing about
sensors or switches; other code decides what messages mean.

| | |
|---|---|
| `config/mqtt.php` | Broker host/port/login (`MQTT_HOST`, `MQTT_PORT`, …) and the topics to listen to (`MQTT_SUBSCRIBE`, default `home/+/+`). |
| `MqttConnector` | `connect('purpose')` returns a connected [php-mqtt/client](https://github.com/php-mqtt/client) client. Used for publishing too. |
| `php artisan mqtt:listen` | Long-running: subscribes and fires `Events\MessageReceived(topic, payload)` for every message. `-v` prints each message. `--topic=` overrides the config. |

If the broker disappears, `mqtt:listen` exits with an error and Docker restarts it
(`restart: unless-stopped`), which reconnects and re-subscribes.

In this project the web app listens for `MessageReceived`, lets the sensors package validate the
reading, and broadcasts it to the dashboard through Reverb (`web/app/Listeners/BroadcastSensorReading.php`).

## Tests

```bash
docker compose exec -w /packages/mqtt web sh -c "composer install && vendor/bin/phpunit"
```

The broker is replaced by a fake client, so tests need no Mosquitto.
