# Home Sensor Platform

[![CI](https://github.com/joelfrens/home-automation/actions/workflows/ci.yml/badge.svg)](https://github.com/joelfrens/home-automation/actions/workflows/ci.yml)

End-to-end IoT telemetry pipeline: ESP32 sensor nodes publish environmental readings over MQTT; a TypeScript ingestion service validates and persists them to PostgreSQL; Grafana visualises them. Fully containerised, configuration-as-code, designed to run unchanged on a Raspberry Pi 5.

<!-- TODO: add photos/screenshots, e.g.
![Sensor node](docs/images/sensor.jpg)
![Grafana dashboard](docs/images/dashboard.png) -->

## Architecture

```mermaid
flowchart LR
    A[ESP32 + BME280] -->|MQTT QoS 0| B[Mosquitto]
    B -->|home/+/+ QoS 1| C[Ingestion service]
    C -->|batched INSERT| D[(PostgreSQL)]
    D --> E[Grafana]
    D --> G[Laravel + React dashboard]
    D --> F[Adminer]
```

| Layer | Technology | Location |
|---|---|---|
| Device | ESP32 (M5Stack ATOM Lite), Bosch BME280 over I2C, Arduino C++, PubSubClient | `firmware/` |
| Messaging | MQTT, Eclipse Mosquitto 2 | `mosquitto/` |
| Ingestion | Node.js, TypeScript (ESM / NodeNext), mqtt.js, Zod 4, node-postgres, Vitest | `ingestion/` |
| Storage | PostgreSQL 17, schema owned by Laravel migrations | `packages/sensors/`, `web/database/` |
| Web app | Laravel 13, React 19 (Inertia 3), TypeScript, Tailwind 4, Recharts, FrankenPHP, Pest | `web/` |
| Packages | Composer packages for non-UI logic, tested with Orchestra Testbench | `packages/` |
| Visualisation | Grafana (provisioned datasource + dashboard), Adminer | `grafana/` |
| Infrastructure | Docker Compose | `docker-compose.yml` |

## Design highlights

- **Decoupled receive/store path.** MQTT messages are pushed into an in-memory write buffer and flushed with multi-row `INSERT`s, so broker throughput never blocks on database latency.
- **Fault tolerance.** The ingestion service auto-reconnects to the broker, retries failed DB writes on a fixed interval while keeping readings queued, applies a bounded buffer that drops oldest-first under sustained outage, and drains the queue on `SIGINT`/`SIGTERM`.
- **Validation at the edge of the system.** Topics are parsed against `home/<room>/<sensor>` with a strict name pattern; payloads are validated with Zod schemas generated from a sensor-type registry (`packages/sensors/sensor-types.json`) using each sensor's physical operating ranges. Unknown sensor types and invalid messages are logged and counted, never stored.
- **Generic, extensible storage.** One narrow `measurements` table (one row per metric per message) serves every sensor type; adding a sensor type is a registry entry, not a schema change. The same registry drives ingestion validation and the dashboard's labels and units.
- **Modular backend.** Non-UI logic lives in Composer packages (`packages/`), auto-discovered by the Laravel host app via a path repository and tested in isolation with Orchestra Testbench; the host app owns only routes, controllers and React pages.
- **Resilient firmware.** Non-blocking main loop (no `delay()` in steady state), automatic WiFi and MQTT reconnection, unique client ID derived from the chip's eFuse MAC, RGB LED status. Credentials are kept out of source control in an untracked `secrets.h`.
- **Configuration as code.** Grafana's datasource (fixed UID) and dashboard are provisioned from the repo; the schema is applied by Laravel migrations; every runtime setting in the ingestion service is overridable by environment variable. A fresh `docker compose up` reproduces the full stack.
- **Observability.** Periodic `[stats]` log line (received / stored / rejected / waiting / dropped) and a Grafana "seconds since last reading" panel per room that alerts visually after 30 s of silence.
- **Horizontal by design.** Adding a sensor node only requires a new `ROOM` value; the wildcard subscription, schema and dashboard (room template variable) pick it up automatically.

## Quick start

**Prerequisites:** Docker (Compose v2), Node.js 20+, Arduino IDE 2 with the *esp32 by Espressif* core 3.x.

```bash
git clone https://github.com/joelfrens/home-automation.git && cd home-automation

# 1. Infrastructure + web app: Mosquitto, PostgreSQL, Grafana, Adminer, Laravel, Vite
cp web/.env.example web/.env      # set ADMIN_PASSWORD, then: docker compose exec web php artisan key:generate
docker compose up -d
docker compose exec web composer install
docker compose exec web php artisan migrate   # creates all tables, including measurements
docker compose exec web php artisan db:seed   # creates the login from ADMIN_* in web/.env

# 2. Ingestion service (watch mode)
cd ingestion && npm install && npm run dev

# 3. Optional: simulated sensors, no hardware required (separate terminal)
npm run simulate                                   # rooms: kitchen, bedroom
ROOMS=office,garage INVALID_RATE=0.1 npm run simulate
```

| Service | URL / port | Credentials (dev only) |
|---|---|---|
| Dashboard (Laravel + React) | http://localhost:8000 | `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `web/.env` |
| Grafana | http://localhost:3001 → *Dashboards → Home → Home sensors* | `admin` / `admin` |
| Adminer | http://localhost:8081 (System: PostgreSQL, Server: `postgres`) | `home` / `home_dev_password` |
| PostgreSQL | `localhost:5433` (container 5432) | `home` / `home_dev_password`, db `home` |
| Mosquitto | `localhost:1883` | anonymous |

> PostgreSQL is mapped to host port **5433** to avoid colliding with a locally installed Postgres on 5432.

### Flashing a sensor node

1. Install libraries: **Adafruit BME280** (+ Unified Sensor, BusIO) and **PubSubClient** (Nick O'Leary).
2. `cp firmware/atom_bme280_mqtt/secrets.example.h firmware/atom_bme280_mqtt/secrets.h` and set `WIFI_NAME` / `WIFI_PASSWORD` (2.4 GHz only).
3. In `atom_bme280_mqtt.ino`, set `MQTT_HOST` to the broker host's LAN IP (`ipconfig getifaddr en0` on macOS) and `ROOM` (`[a-z0-9-]+`).
4. Board **ESP32 Dev Module**, Upload Speed **115200** (921600 fails on the ATOM Lite), then upload.

Hardware: BME280 on the ATOM Lite Grove port (SDA = G26, SCL = G32), I2C address `0x76` with fallback `0x77`. RGB LED on G27.

| LED | State |
|---|---|
| Blue | Connecting to WiFi / broker |
| Green blink (150 ms) every 5 s | Reading published |
| Red (latched until next success) | BME280 missing, broker unreachable or publish failed |

## Reference

### MQTT contract

| | |
|---|---|
| Topic | `home/<room>/<sensor>`, e.g. `home/living-room/bme280` — segments match `^[a-z0-9-]{1,40}$` |
| Payload | `{"temperature":22.70,"humidity":40.09,"pressure":984.88}` — °C, %RH, hPa |
| Valid ranges | temperature −40…85, humidity 0…100, pressure 300…1100 |
| Rate | one message per node every 5 s |

```bash
# Watch traffic / inject a test message
docker exec -it home-mosquitto mosquitto_sub -t 'home/#' -v
docker exec home-mosquitto mosquitto_pub -t 'home/test/bme280' -m '{"temperature":21,"humidity":40,"pressure":1000}'
```

### Database schema

```sql
CREATE TABLE measurements (
    id          BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ(3)   NOT NULL DEFAULT now(),
    room        TEXT             NOT NULL,
    sensor      TEXT             NOT NULL,   -- sensor type from the topic, e.g. bme280
    metric      TEXT             NOT NULL,   -- e.g. temperature
    value       DOUBLE PRECISION NOT NULL,
    unit        TEXT             NOT NULL    -- e.g. °C, copied from the registry
);
CREATE INDEX ON measurements (room, sensor, metric, recorded_at);
CREATE INDEX ON measurements (recorded_at);
```

Created by the sensors package migration (`php artisan migrate`). One BME280 message becomes three rows sharing `recorded_at`, which is the ingestion time (nodes have no RTC).

**Sensor types** are defined once in `packages/sensors/sensor-types.json` (metrics with label, unit, min, max) and read by both the ingestion service (validation) and Laravel (labels, units). Adding e.g. a water-level sensor means adding a registry entry and publishing to `home/<room>/water-level`, with no schema change.

### Ingestion configuration

| Variable | Default | Purpose |
|---|---|---|
| `MQTT_URL` | `mqtt://localhost:1883` | Broker |
| `MQTT_TOPIC` | `home/+/+` | Subscription filter |
| `SENSOR_TYPES_PATH` | `../packages/sensors/sensor-types.json` | Sensor-type registry (shared with Laravel) |
| `DATABASE_URL` | `postgres://home:home_dev_password@localhost:5433/home` | PostgreSQL |
| `MAX_BUFFER` | `10000` | Readings held in memory during a DB outage before oldest are dropped |
| `RETRY_MS` | `5000` | Delay between failed write retries |
| `STATS_EVERY_MS` | `60000` | Interval of the `[stats]` log line |

Scripts: `npm run dev` (tsx watch), `npm run build` + `npm start` (compiled), `npm run typecheck`, `npm run simulate`, `npm test` / `npm run test:watch`.

### Tests

```bash
cd ingestion && npm test                                                               # 47 Vitest tests
docker compose exec web php artisan test                                               # Laravel app (Pest/PHPUnit)
docker compose exec -w /packages/sensors web sh -c "composer install && vendor/bin/phpunit"   # sensors package
```

All of these, plus linting, type checks, static analysis and an ESP32 firmware compile, run in GitHub Actions on every push ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)).

No test touches real infrastructure:

- **Ingestion (Vitest).** Topic parsing, the sensor-type registry, payload validation generated from it (boundary values, wrong types, missing fields, unknown sensor types, extra fields stripped), message → measurement rows, and the write buffer (batching, retry without hammering, re-queue order, oldest-first drop, drain on shutdown) using a fake writer and Vitest fake timers. The buffer suite was mutation-checked.
- **Sensors package (PHPUnit + Orchestra Testbench).** Registry loading and validation, ranges, latest-per-room, bucketed history averages, recent messages, on in-memory SQLite.
- **Laravel app (Pest/PHPUnit).** Auth flows, disabled registration, dashboard page props.
- **Safety guard.** Both PHP suites force in-memory SQLite and refuse to run `RefreshDatabase` against any other database (added after a test run once wiped the development database).

### Repository layout

```
firmware/atom_bme280_mqtt/       ESP32 sketch; secrets.example.h → secrets.h (git-ignored)
ingestion/src/
  index.ts                       MQTT client, message pipeline, stats, graceful shutdown
  schema.ts                      Topic parser, sensor-type registry, generated Zod validators
  measurements.ts                Message → one row per metric
  buffer.ts                      Generic bounded write buffer with retry
  db.ts                          pg pool + batched insert into measurements
  config.ts                      Env-driven configuration
  simulator.ts                   Fake multi-room publisher with drift and invalid-message injection
ingestion/test/                  Vitest unit tests
packages/sensors/                joelfrens/home-automation-sensors: registry, Measurement model,
                                 migration, SensorReadings queries, Testbench tests
web/                             Laravel 13 + React host app (Dockerfile, dashboard, auth)
mosquitto/mosquitto.conf         Broker config
grafana/provisioning/
  datasources/postgres.yml       Datasource (uid: home-postgres)
  dashboards/                    Provider + dashboard JSON
docker-compose.yml               Mosquitto, PostgreSQL, Grafana, Adminer, web, vite
```

### Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Node logs `failed (state -2)` | Broker unreachable: wrong `MQTT_HOST`, Docker not running, or host firewall blocking 1883 |
| Ingestion logs `database write failed` | Postgres not up, `measurements` not created yet (`docker compose exec web php artisan migrate`), or the client is hitting a different Postgres on 5432 — use 5433 |
| Ingestion logs `unknown sensor type` | The topic's sensor segment is not in `packages/sensors/sensor-types.json`; add it and restart ingestion |
| Dashboard page is blank | Vite (port 5173) not running, or the page is opened from another device (dev assets are served from `localhost:5173`) |
| Grafana rejects `admin`/`admin` | `GF_SECURITY_ADMIN_PASSWORD` only applies on first DB init: `docker exec home-grafana grafana cli admin reset-admin-password admin` |
| Changing a provisioned datasource UID breaks Grafana startup | Add a one-off `deleteDatasources` entry for the old datasource, restart, then remove it |
| Upload fails / no serial port | Use Upload Speed 115200 and a data-capable USB-C cable |

## Security

Development configuration for a trusted LAN: Mosquitto accepts anonymous clients and all credentials in `docker-compose.yml` are defaults. Before exposing anything beyond the local network, enable Mosquitto authentication (and TLS), rotate the database and Grafana passwords, and move them into an untracked `.env`.

## Roadmap

- [x] ESP32 firmware publishing over MQTT
- [x] Docker stack: Mosquitto, PostgreSQL, Grafana, Adminer
- [x] TypeScript ingestion service with validation, batching and retry
- [x] Grafana dashboard provisioned as code
- [x] Laravel + React (Inertia) web app in Docker: login, sensor dashboard (cards, history chart, recent readings)
- [ ] Live updates via Reverb; MQTT command path to devices (lights/switches) in a separate `switches` package
- [ ] AI features over the collected data
- [ ] Deploy to Raspberry Pi 5: Docker, systemd for ingestion, Chromium kiosk on the attached display
- [x] Unit tests for ingestion (validation, buffer retry/drop behaviour)
- [x] CI with GitHub Actions on every push: ingestion (Node 22 & 24), Laravel app (lint, types, Pint, PHPStan, tests), sensors package tests, ESP32 firmware compile, Compose validation
- [ ] Node-side buffering with NTP timestamps to survive WiFi outages without data gaps
- [ ] OTA firmware updates: ArduinoOTA for development, then MQTT-triggered HTTP OTA with signed images for fleet updates
- [x] Generic `measurements` storage and sensor-type registry, ready for more sensor types (water level, air quality)
- [ ] Music: OwnTone streaming Spotify and local media to AirPlay 2 speakers (HomePod mini), controlled from the dashboard via its JSON API
- [ ] Long-range LoRa node (SX1262, 868 MHz) with a LoRa → MQTT gateway for sites without WiFi
