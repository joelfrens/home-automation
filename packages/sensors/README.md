# joelfrens/home-automation-sensors

Laravel package that owns sensor data for the home automation project: the registry of sensor
types, the `measurements` table, and the queries the dashboard needs. No UI; the host app renders
the results.

## What it provides

| | |
|---|---|
| `sensor-types.json` | Registry of sensor types → metrics (label, unit, valid min/max). Also read by the Node ingestion service to validate MQTT payloads, so both sides agree. |
| `SensorTypes` | Loads and queries the registry (`has()`, `metric()`, `metrics()`). |
| `SensorReadings` | `latestPerRoom()`, `history(Range)` (time-bucketed averages), `recent($limit)`. Returns plain arrays. |
| `SensorMessage` | `parse($topic, $payload)` → `{room, sensor, values}` or throws `InvalidSensorMessage` with the reason. Same rules as ingestion (topic pattern, known type, every metric a number in range, extras dropped). Used to broadcast live readings. |
| `Range` | `6h` / `24h` / `7d` with bucket sizes; `Range::fromQuery()` falls back to 24h. |
| `Models\Measurement` | Eloquent model for `measurements`. |
| Migration | `measurements(id, recorded_at, room, sensor, metric, value, unit)`, one row per metric per message. |
| `config/sensors.php` | Registry path (`SENSOR_TYPES_PATH`), stale/offline thresholds, latest-reading lookback. |

`SensorsServiceProvider` is auto-discovered; it registers `SensorTypes` and `SensorReadings` as
singletons and loads the migration.

## Adding a sensor type

1. Add it to `sensor-types.json`, e.g.
   `"water-level": { "label": "Water level", "metrics": { "level": { "label": "Level", "unit": "cm", "min": 0, "max": 300 } } }`
2. Publish from the device to `home/<room>/water-level` with `{"level": 42}`.

No schema change, and the dashboard picks the new metric up automatically. Restart ingestion to reload the registry.

## Installing in the host app

`web/composer.json` has a path repository (`../packages/*`, symlinked), then:

```bash
docker compose exec web composer require joelfrens/home-automation-sensors:@dev
docker compose exec web php artisan migrate
```

## Tests

Orchestra Testbench (a minimal Laravel app with only this package), in-memory SQLite:

```bash
docker compose exec -w /packages/sensors web sh -c "composer install && vendor/bin/phpunit"
```

The base `TestCase` refuses to run against anything except in-memory SQLite, so tests can never
wipe a real database.
