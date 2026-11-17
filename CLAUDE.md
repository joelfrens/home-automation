# Project context for Claude

## About this project
Home automation platform covering IoT, messaging, backend, databases and frontend.
Explain new concepts simply and give step-by-step instructions. Development machine: Apple M1 Mac mini
(IP 192.168.1.154). Final deployment target: Raspberry Pi 5 with its own screen.

## Architecture (planned)
ESP32 ATOM Lite + BME280 -> MQTT (Mosquitto) -> Node.js + TypeScript ingestion
-> PostgreSQL -> Grafana (monitoring) and Laravel + React (custom dashboard, AI features,
other home automation such as lights/switches).
Development can use simulated readings; physical sensors run alongside.

## Hardware
- M5Stack ATOM Lite (ESP32-PICO-D4), Grove BME280 (temperature, humidity, pressure)
- Grove port: SDA = G26, SCL = G32. RGB status LED = G27. Button = G39. IR LED = G12.
- BME280 I2C address 0x76 (fallback 0x77)
- USB serial port on the Mac: /dev/cu.usbserial-6152DAC04F
- Arduino IDE: board "ESP32 Dev Module", Upload Speed MUST be 115200 (921600 fails)
- Libraries: Adafruit BME280 (+ Unified Sensor, BusIO), PubSubClient
- WiFi must be 2.4 GHz

## Current status
- [x] Firmware publishes every 5 s over MQTT (firmware/atom_bme280_mqtt/)
      Topic: home/<room>/bme280, e.g. home/living-room/bme280
      Payload: {"temperature":22.70,"humidity":40.09,"pressure":984.88}
      LED: blue = connecting, short green blink every 5 s = sent, red (stays on) = problem
- [x] docker-compose.yml: Mosquitto :1883, PostgreSQL 17 :5433 (host port; 5432 is taken by Homebrew Postgres), Grafana :3001 (admin/admin)
      DB: user home / password home_dev_password / database home
      Table sensor_readings(id, room, sensor, temperature_c, humidity_pct, pressure_hpa, recorded_at)
      Created by db/init.sql (Laravel migrations should take ownership later)
- [x] ingestion/ (TypeScript, ESM, NodeNext): subscribes home/+/bme280, validates with Zod,
      buffers in memory and batch-inserts to Postgres, retries if DB is down, drops oldest
      when buffer is full, graceful shutdown. npm run dev / npm run simulate
- [x] Grafana dashboards (provisioned from grafana/provisioning/dashboards/files/)
- [ ] Laravel + React app (Inertia starter kit, Reverb for live updates,
      Laravel publishes MQTT commands to devices)
- [ ] AI features
- [ ] Deploy to Raspberry Pi 5 (Docker, systemd, Chromium kiosk mode on its screen)
- [ ] Push to GitHub with README, diagram and photos

## Useful commands
- Start infrastructure: docker compose up -d
- Watch MQTT traffic: docker exec -it home-mosquitto mosquitto_sub -t 'home/#' -v
- Send a test message: docker exec home-mosquitto mosquitto_pub -t 'home/test/bme280' -m '{"temperature":21,"humidity":40,"pressure":1000}'
- Browse DB in browser: Adminer http://localhost:8081 (PostgreSQL, server postgres, user home, db home)
- Latest rows: docker exec -it home-postgres psql -U home -c "SELECT * FROM sensor_readings ORDER BY recorded_at DESC LIMIT 5;"

## Conventions
- Room/sensor names: lowercase letters, numbers and dashes only
- Mosquitto allows anonymous access: development only, add auth before exposing beyond LAN
- Keep docker-compose portable so the same stack runs on the Pi 5
- Git: Claude may commit when asked but must NEVER run git push. The owner pushes manually
  (remote: https://github.com/joelfrens/home-automation, branch main)
