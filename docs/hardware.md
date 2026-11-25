# Hardware and firmware

## Sensor node

- **Board:** M5Stack ATOM Lite (ESP32-PICO-D4)
- **Sensor:** Grove BME280 (temperature, humidity, pressure), I2C address `0x76` (fallback `0x77`)
- **Pins:** Grove port SDA = G26, SCL = G32. RGB status LED = G27. Button = G39. IR LED = G12.
- **WiFi:** 2.4 GHz only

Firmware: [`firmware/atom_bme280_mqtt/`](../firmware/atom_bme280_mqtt). It publishes every 5 s to
`home/<room>/bme280`, e.g. `{"temperature":22.70,"humidity":40.09,"pressure":984.88}`.

| LED | Meaning |
|---|---|
| Blue | Connecting to WiFi / broker |
| Short green blink every 5 s | Reading sent |
| Red (stays on) | Sensor missing, broker unreachable or publish failed |

On the serial monitor (115200 baud), `failed (state -2)` means the broker can't be reached and
`state 4`/`5` means the MQTT login in `secrets.h` is wrong.

## Flashing

1. Arduino IDE 2 with the **esp32 by Espressif** core 3.x.
2. Libraries: **Adafruit BME280** (+ Adafruit Unified Sensor, Adafruit BusIO), **PubSubClient**.
3. `cp firmware/atom_bme280_mqtt/secrets.example.h firmware/atom_bme280_mqtt/secrets.h` and fill in
   your WiFi and the MQTT login (user `sensor`, password = `MQTT_SENSOR_PASSWORD` from the repo-root
   `.env`). `secrets.h` is git-ignored; never commit it.
4. In the sketch, set `MQTT_HOST` (the LAN IP of the machine running Docker) and `ROOM`
   (lowercase, numbers, dashes).
5. Board **ESP32 Dev Module**, **Upload Speed 115200** (921600 fails on the ATOM Lite), Partition
   Scheme **Default 4MB with spiffs** (keeps OTA possible; not "Huge APP"). Use a data USB-C cable.

CI compiles the sketch with `arduino-cli` (esp32 core 3.3.12) using the placeholder secrets.

## Adding a sensor type

Add it to [`packages/sensors/sensor-types.json`](../packages/sensors/sensor-types.json) (metrics
with label, unit, min, max) and publish to `home/<room>/<sensor-type>`. Ingestion, the database and
the dashboard pick it up; no schema change.

## Plans

### OTA firmware updates

1. **ArduinoOTA:** upload from the Arduino IDE over WiFi (network port), with an OTA password; the
   firmware publishes a `FW_VERSION` at boot. The first OTA-capable build must go over USB.
2. **From the web app:** an MQTT command (e.g. `home/<room>/<sensor>/cmd`) makes the device download a
   signed `.bin` over HTTP and report its new version; fleet updates from the dashboard.

### LoRa long-range sensor

For places without WiFi. Point-to-point LoRa (no LoRaWAN gateway): a remote board reads a BME280 and
sends over LoRa; a gateway board at home publishes to MQTT with the same topic and payload, so nothing
else changes. Each board's OLED shows the last reading and signal strength (RSSI). Later: buffering
on the device with NTP timestamps to fill gaps.

Parts (about £50–75):

- 2× Heltec WiFi LoRa 32 V3 (ESP32-S3 + SX1262 + OLED), **868 MHz** (UK/EU), with antennas.
  Library: RadioLib. **Never power on without the antenna attached.**
- 1× real BME280 (not a BMP280, which has no humidity): Grove BME280 + Grove-to-jumper cable, or an
  Adafruit/Pimoroni/SparkFun breakout
- Optional: 3.7 V LiPo with JST 1.25 mm plug (check polarity), jumper wires

Build in stages: LoRa "hello" between the boards → sensor readings → gateway into MQTT.

### Music

OwnTone in Docker streams to AirPlay 2 speakers (e.g. HomePod mini) from Spotify (Premium), local
files or internet radio; a dashboard panel uses OwnTone's JSON API. Needs `network_mode: host` for
speaker discovery.
