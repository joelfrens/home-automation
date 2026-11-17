# Home Sensor Platform

End-to-end IoT telemetry pipeline: ESP32 sensor nodes publish environmental readings over MQTT; a TypeScript ingestion service validates and persists them to PostgreSQL; Grafana visualises them. Fully containerised, configuration-as-code, designed to run unchanged on a Raspberry Pi 5.

<!-- TODO: add photos/screenshots, e.g.
![Sensor node](docs/images/sensor.jpg)
![Grafana dashboard](docs/images/dashboard.png) -->

## Architecture

```mermaid
flowchart LR
    A[ESP32 + BME280] -->|MQTT QoS 0| B[Mosquitto]
    B -->|home/+/bme280 QoS 1| C[Ingestion service]
    C -->|batched INSERT| D[(PostgreSQL)]
    D --> E[Grafana]
    D --> F[Adminer]
```

| Layer | Technology | Location |
|---|---|---|
| Device | ESP32 (M5Stack ATOM Lite), Bosch BME280 over I2C, Arduino C++, PubSubClient | `firmware/` |
| Messaging | MQTT, Eclipse Mosquitto 2 | `mosquitto/` |
| Ingestion | Node.js, TypeScript (ESM / NodeNext), mqtt.js, Zod 4, node-postgres, Vitest | `ingestion/` |
| Storage | PostgreSQL 17 | `db/` |
| Visualisation | Grafana (provisioned datasource + dashboard), Adminer | `grafana/` |
| Infrastructure | Docker Compose | `docker-compose.yml` |

## Design highlights

- **Decoupled receive/store path.** MQTT messages are pushed into an in-memory write buffer and flushed with multi-row `INSERT`s, so broker throughput never blocks on database latency.
- **Fault tolerance.** The ingestion service auto-reconnects to the broker, retries failed DB writes on a fixed interval while keeping readings queued, applies a bounded buffer that drops oldest-first under sustained outage, and drains the queue on `SIGINT`/`SIGTERM`.
- **Validation at the edge of the system.** Topics are parsed against `home/<room>/<sensor>` with a strict name pattern; payloads are validated with Zod against the BME280's physical operating ranges. Invalid messages are logged and counted, never stored.
- **Resilient firmware.** Non-blocking main loop (no `delay()` in steady state), automatic WiFi and MQTT reconnection, unique client ID derived from the chip's eFuse MAC, RGB LED status. Credentials are kept out of source control in an untracked `secrets.h`.
- **Configuration as code.** Grafana's datasource (fixed UID) and dashboard are provisioned from the repo; schema is applied by `db/init.sql`; every runtime setting in the ingestion service is overridable by environment variable. A fresh `docker compose up` reproduces the full stack.
- **Observability.** Periodic `[stats]` log line (received / stored / rejected / waiting / dropped) and a Grafana "seconds since last reading" panel per room that alerts visually after 30 s of silence.
- **Horizontal by design.** Adding a sensor node only requires a new `ROOM` value; the wildcard subscription, schema and dashboard (room template variable) pick it up automatically.

## Quick start

**Prerequisites:** Docker (Compose v2), Node.js 20+, Arduino IDE 2 with the *esp32 by Espressif* core 3.x.

```bash
git clone https://github.com/joelfrens/home-automation.git && cd home-automation

# 1. Infrastructure: Mosquitto, PostgreSQL, Grafana, Adminer
docker compose up -d

# 2. Ingestion service (watch mode)
cd ingestion && npm install && npm run dev

# 3. Optional: simulated sensors, no hardware required (separate terminal)
npm run simulate                                   # rooms: kitchen, bedroom
ROOMS=office,garage INVALID_RATE=0.1 npm run simulate
```

| Service | URL / port | Credentials (dev only) |
|---|---|---|
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
CREATE TABLE sensor_readings (
    id            BIGSERIAL PRIMARY KEY,
    room          TEXT        NOT NULL,
    sensor        TEXT        NOT NULL,
    temperature_c REAL        NOT NULL,
    humidity_pct  REAL        NOT NULL,
    pressure_hpa  REAL        NOT NULL,
    recorded_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX sensor_readings_room_time_idx ON sensor_readings (room, recorded_at DESC);
```

Applied by `db/init.sql` on first start of an empty volume. `recorded_at` is the ingestion time (nodes have no RTC).

### Ingestion configuration

| Variable | Default | Purpose |
|---|---|---|
| `MQTT_URL` | `mqtt://localhost:1883` | Broker |
| `MQTT_TOPIC` | `home/+/bme280` | Subscription filter |
| `DATABASE_URL` | `postgres://home:home_dev_password@localhost:5433/home` | PostgreSQL |
| `MAX_BUFFER` | `10000` | Readings held in memory during a DB outage before oldest are dropped |
| `RETRY_MS` | `5000` | Delay between failed write retries |
| `STATS_EVERY_MS` | `60000` | Interval of the `[stats]` log line |

Scripts: `npm run dev` (tsx watch), `npm run build` + `npm start` (compiled), `npm run typecheck`, `npm run simulate`, `npm test` / `npm run test:watch`.

### Tests

```bash
cd ingestion && npm test
```

41 Vitest unit tests, no infrastructure required (the database writer is replaced with a fake, timers with Vitest fake timers), run in well under a second:

- **`test/schema.test.ts`** — topic parsing (segment count, prefix, allowed characters, length limit) and payload validation (BME280 boundary values, out-of-range values, wrong types, missing fields, unknown fields stripped).
- **`test/buffer.test.ts`** — write buffer behaviour: immediate write, batching while a write is in flight, `batchSize` limit, retry after `retryMs` without hammering the DB, failed batch re-queued at the front in order, recovery after repeated failures, oldest-first drop when full, and `drain()` on shutdown (cancels pending retry, batches, waits for in-flight write, never throws).

The suite was mutation-checked: deliberately breaking re-queue order, drop order or the retry back-off each causes a failing test.

### Repository layout

```
firmware/atom_bme280_mqtt/       ESP32 sketch; secrets.example.h → secrets.h (git-ignored)
ingestion/src/
  index.ts                       MQTT client, message pipeline, stats, graceful shutdown
  schema.ts                      Topic parser + Zod payload schema
  buffer.ts                      Bounded write buffer with retry
  db.ts                          pg pool + batched insert
  config.ts                      Env-driven configuration
  simulator.ts                   Fake multi-room publisher with drift and invalid-message injection
ingestion/test/                  Vitest unit tests (schema, write buffer)
db/init.sql                      Schema and index
mosquitto/mosquitto.conf         Broker config
grafana/provisioning/
  datasources/postgres.yml       Datasource (uid: home-postgres)
  dashboards/                    Provider + dashboard JSON
docker-compose.yml               Mosquitto, PostgreSQL, Grafana, Adminer
```

### Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Node logs `failed (state -2)` | Broker unreachable: wrong `MQTT_HOST`, Docker not running, or host firewall blocking 1883 |
| Ingestion logs `database write failed` | Postgres not up, or the client is hitting a different Postgres on 5432 — use 5433 |
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
- [ ] Laravel + React (Inertia) web app with live updates via Reverb; MQTT command path to devices (lights/switches)
- [ ] AI features over the collected data
- [ ] Deploy to Raspberry Pi 5: Docker, systemd for ingestion, Chromium kiosk on the attached display
- [x] Unit tests for ingestion (validation, buffer retry/drop behaviour)
- [ ] Run tests in CI (GitHub Actions) on every push
- [ ] Node-side buffering with NTP timestamps to survive WiFi outages without data gaps
- [ ] Long-range LoRa node (SX1262, 868 MHz) with a LoRa → MQTT gateway for sites without WiFi
