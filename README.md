# Home Automation – Sensor Platform

Learning project: ESP32 sensors → MQTT → TypeScript ingestion → PostgreSQL → Grafana / Laravel + React.

    ATOM Lite + BME280 ──MQTT──► Mosquitto ──► Node.js/TS ingestion ──► PostgreSQL ──► Grafana
       (firmware/)                (Docker)        (ingestion/)            (Docker)       (Docker)

## Run it

    docker compose up -d            # Mosquitto :1883, PostgreSQL :5433, Grafana :3001, Adminer (DB web UI) :8081
    cd ingestion && npm install
    npm run dev                     # ingestion service
    npm run simulate                # (optional, second terminal) fake sensors

Grafana: http://localhost:3001 (admin / admin), PostgreSQL datasource pre-configured.

## MQTT topics

`home/<room>/bme280` with payload `{"temperature":22.7,"humidity":40.1,"pressure":984.9}`

## Ingestion service

- Subscribes to `home/+/bme280`
- Validates every message with Zod (type and physical range checks)
- Buffers readings in memory and writes to PostgreSQL in batches
- Survives broker or database outages: reconnects and retries, drops oldest readings only if the buffer fills
- Graceful shutdown saves queued readings

## Roadmap

- [x] ESP32 firmware publishing over MQTT
- [x] Docker stack: Mosquitto, PostgreSQL, Grafana
- [x] TypeScript ingestion service with validation and retry
- [x] Grafana dashboards (provisioned from grafana/provisioning/dashboards/files/)
- [ ] Laravel + React dashboard (Inertia, Reverb for live updates)
- [ ] AI features
- [ ] Deploy to Raspberry Pi 5
