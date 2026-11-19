# Project context for Claude

## About this project
Home automation learning project to gain CV-relevant experience with IoT, messaging,
backend, databases and frontend. Owner is learning as they go: explain new concepts
simply and give step-by-step instructions. Development machine: Apple M1 Mac mini
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
      Tests: ingestion/test/ (Vitest 4, 41 unit tests for schema + buffer, fake timers, no infra). npm test
      Deps: zod 4.6.7 (upgraded from 3 for GHSA-mrhx-c7xc-m3w5). Known dev-only advisory: picomatch
      via vite/vitest, no fix yet; npm audit fix --force suggests downgrading vitest, do NOT do that.
- [x] Grafana dashboards (provisioned from grafana/provisioning/dashboards/files/)
- [ ] Laravel + React app (Inertia starter kit, Reverb for live updates,
      Laravel publishes MQTT commands to devices)
- [ ] AI features
- [ ] Deploy to Raspberry Pi 5 (Docker, systemd, Chromium kiosk mode on its screen)
- [ ] Push to GitHub with README, diagram and photos
- [ ] OTA firmware updates (later)
      Stage 1: ArduinoOTA – push from Arduino IDE over WiFi (network port), OTA password,
      FW_VERSION constant published at boot. First OTA-capable build must go over USB.
      Keep Partition Scheme "Default 4MB with spiffs" (OTA-capable; NOT "Huge APP (no OTA)").
      Stage 2 (with Laravel/MQTT command path): device pulls signed .bin over HTTP when sent an
      MQTT command (e.g. home/<room>/<sensor>/cmd), reports new version; fleet update from web app.
- [ ] Multi-sensor ingestion (later): water level, air quality, etc.
      Only schema.ts (fixed BME280 payload) and sensor_readings/db.ts (fixed columns) are
      BME280-specific; MQTT, buffer, retry, shutdown are generic and reusable.
      Proposed: per-sensor-type Zod registry keyed by the topic's <sensor> segment (unknown types
      rejected + logged) and a generic narrow table measurements(time, room, sensor, metric,
      value, unit). Decide before Laravel migrations take ownership of the schema. Not yet agreed
      with owner – confirm storage design first.
- [ ] Music in the dashboard (later, with Pi deployment)
      Speaker: Apple HomePod mini – AirPlay 2 only (no AUX; Bluetooth is setup-only).
      Plan: OwnTone in docker-compose (network_mode: host for mDNS discovery) streams to the
      HomePod over AirPlay 2 (~2 s latency). Sources: Spotify via OwnTone's built-in Spotify
      support (needs Premium), local files, internet radio. Dashboard "Music" panel calls
      OwnTone's JSON API (play/pause/skip/volume/outputs/now playing + artwork).
      Home app: HomePod speaker access must allow devices on the same network.
      Multi-room later by adding AirPlay speakers. Apple Music: native on HomePod but no public
      control API – out of scope. Spotify Web Playback SDK / librespot only relevant for
      non-AirPlay speakers (Pi 5 has no 3.5 mm jack – needs USB DAC/HAT).
- [ ] Bonus: LoRa long-range sensor, for places without WiFi (not bought yet)
      Design: point-to-point LoRa (no LoRaWAN gateway). Remote board reads BME280 and sends over LoRa;
      home gateway board receives and publishes to MQTT on the same topic/payload, so ingestion,
      Postgres and Grafana stay unchanged. Show last reading / RSSI on each board's OLED.
      Later: on-device buffering + NTP timestamps (payload gets a time field) for offline gaps.
      Shopping list (approx. £50–75 total):
        - 2x Heltec WiFi LoRa 32 V3 (ESP32-S3 + SX1262 + OLED), 868 MHz version (UK/EU), with antenna
          Library: RadioLib. Never power on without the antenna attached.
        - 1x BME280 that really measures humidity (not a BMP280): Grove BME280 + Grove-to-female-jumper
          cable, or an Adafruit/Pimoroni/SparkFun breakout
        - Optional: 3.7 V LiPo with JST 1.25 mm plug (check polarity), female-female jumper wires
      Build in stages: LoRa "hello" between boards -> sensor readings -> gateway into MQTT.

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
