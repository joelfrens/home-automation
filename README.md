# Home Sensor Dashboard

Measure the **temperature, humidity and air pressure** in your rooms with a small wireless sensor, and watch the readings live on a dashboard in your web browser.

<!-- TODO: add a photo of the sensor and a screenshot of the Grafana dashboard here, e.g.
![The sensor](docs/images/sensor.jpg)
![The dashboard](docs/images/dashboard.png) -->

**What you get**

- A sensor that sends a new reading every 5 seconds over your home WiFi
- Every reading saved, so you can look back over hours, days or weeks
- A live dashboard with graphs, current values and an alert colour if the sensor stops reporting
- Room for more sensors later: each one just needs its own room name

---

## How it works (in plain English)

```mermaid
flowchart LR
    A["📟 Sensor<br/>(ATOM Lite + BME280)"] -- WiFi --> B["📮 Mosquitto<br/>message post office"]
    B --> C["⚙️ Ingestion service<br/>checks & saves readings"]
    C --> D["🗄️ PostgreSQL<br/>database"]
    D --> E["📊 Grafana<br/>dashboard"]
```

1. **The sensor** measures the room and sends the numbers over WiFi.
2. **Mosquitto** is like a post office: it receives the sensor's messages and hands them to whoever is interested.
3. **The ingestion service** picks up each message, checks the numbers make sense, and saves them.
4. **PostgreSQL** is the database where every reading is stored.
5. **Grafana** reads the database and draws the dashboard you look at.

Steps 2–5 run on one computer in your home (a Mac now, a Raspberry Pi later). You never need to touch them once they're running.

---

## What you need

### Hardware

| Item | Notes |
|---|---|
| **M5Stack ATOM Lite** | The small square controller with a button and coloured light |
| **Grove BME280 sensor** | The temperature / humidity / pressure sensor |
| **Grove cable** | Usually comes with the sensor (4 wires, white plug at each end) |
| **USB-C data cable** | Must be a *data* cable – some charging-only cables won't work |
| **A computer** | This guide uses a Mac. It must stay switched on for readings to be saved. |
| **2.4 GHz WiFi** | The sensor cannot join 5 GHz networks. Most home routers offer both. |

### Software (all free)

You'll install these in Step 2:

- **Docker Desktop** – runs the post office, database and dashboard for you
- **Node.js** – runs the ingestion service
- **Arduino IDE** – puts the program onto the sensor

---

## Setup

Allow about **45 minutes** the first time. Follow the steps in order.

> **About the Terminal:** some steps ask you to type commands. Open the **Terminal** app (press <kbd>⌘ Cmd</kbd> + <kbd>Space</kbd>, type `Terminal`, press <kbd>Enter</kbd>). Copy each command from this page, paste it into Terminal, and press <kbd>Enter</kbd>. Wait for it to finish before the next one.

### Step 1 – Download this project

**Easiest:** on this GitHub page, click the green **Code** button → **Download ZIP**. Double-click the downloaded file to unzip it, rename the folder to `home-automation`, and move it into your **home folder** (the one with your name, containing Desktop, Documents, etc.).

**Or, if you have git installed**, run this in Terminal:

```
cd ~
git clone https://github.com/joelfrens/home-automation.git
```

Either way you should now have a folder at `~/home-automation`.

### Step 2 – Install the software

1. **Docker Desktop** – download from [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/). Choose **Apple Silicon** (M1/M2/M3/M4 Macs) or **Intel chip** to match your Mac ( → About This Mac). Install it, open it, and wait until it says *Engine running*. You can skip signing in.
2. **Node.js** – download the **LTS** version from [nodejs.org](https://nodejs.org/) and run the installer.
3. **Arduino IDE** – download from [arduino.cc/en/software](https://www.arduino.cc/en/software) and drag it into Applications.

Check Node.js installed correctly by running this in Terminal – it should print a version number like `v22.x.x` or higher:

```
node -v
```

### Step 3 – Start the post office, database and dashboard

Make sure Docker Desktop is open, then run:

```
cd ~/home-automation
docker compose up -d
```

The first time, this downloads a few hundred MB and takes a few minutes. When it finishes you should see four lines ending in **Started** (mosquitto, postgres, grafana, adminer).

> If your Mac asks *"Do you want the application to accept incoming network connections?"*, click **Allow** – the sensor needs to reach your Mac.

These keep running in the background and restart automatically whenever Docker Desktop is open.

### Step 4 – Start the ingestion service

In Terminal, run these one at a time:

```
cd ~/home-automation/ingestion
npm install
npm run dev
```

After a few seconds you should see:

```
[mqtt] connected to mqtt://localhost:1883
[mqtt] subscribed to home/+/bme280
```

**Leave this Terminal window open.** This is the service that saves readings – if you close the window, readings stop being saved. (For your next commands, open a *new* Terminal window with <kbd>⌘ Cmd</kbd> + <kbd>N</kbd>.)

> **No sensor yet?** You can test everything with fake readings. In a new Terminal window run `cd ~/home-automation/ingestion && npm run simulate`. Fake rooms called *kitchen* and *bedroom* will appear on the dashboard. Press <kbd>Ctrl</kbd> + <kbd>C</kbd> to stop it.

### Step 5 – Find your computer's address

The sensor needs to know where to send its readings. In a new Terminal window run:

```
ipconfig getifaddr en0
```

It prints something like `192.168.1.154`. **Write this down** – you'll need it in Step 7.

> Nothing printed? Your Mac might be on a cable instead of WiFi – try `ipconfig getifaddr en1`. Or look in  → System Settings → Wi-Fi → **Details…** next to your network → **IP address**.

### Step 6 – Connect the sensor

1. Plug the **Grove cable** into the BME280 sensor and into the socket on the **bottom** of the ATOM Lite. The plugs only fit one way round.
2. Connect the ATOM Lite to your Mac with the **USB-C cable**.

### Step 7 – Put the program on the sensor

**7a. Set up Arduino IDE (first time only)**

1. Open **Arduino IDE**.
2. Click the **Boards Manager** icon on the left (looks like a circuit board), search for **esp32**, and install **esp32 by Espressif Systems**. This takes a few minutes.
3. Click the **Library Manager** icon (looks like books), and install each of these. If asked to install extra "dependencies", click **Install All**:
   - **Adafruit BME280 Library** by Adafruit
   - **PubSubClient** by Nick O'Leary

**7b. Open the sensor program and add your WiFi details**

1. In Arduino IDE: **File → Open…** and choose
   `home-automation/firmware/atom_bme280_mqtt/atom_bme280_mqtt.ino`
2. In Finder, open the same folder `home-automation/firmware/atom_bme280_mqtt/`. **Duplicate** `secrets.example.h` and rename the copy to exactly **`secrets.h`**. Then close and re-open the program in Arduino IDE – you'll now see a `secrets.h` tab.
3. In the **`secrets.h`** tab, replace `YourWiFiName` and `YourWiFiPassword` with your 2.4 GHz WiFi name and password. Keep the quote marks.
   *This file stays on your computer only – it is never uploaded to GitHub.*
4. In the **main** tab, find the section marked `CHANGE THESE`:
   - **`MQTT_HOST`** – replace the number with the address you wrote down in Step 5.
   - **`ROOM`** – the name of the room the sensor will live in. Use only lowercase letters, numbers and dashes, e.g. `living-room`, `bedroom-2`.
5. Save with <kbd>⌘ Cmd</kbd> + <kbd>S</kbd>.

**7c. Upload**

1. **Tools → Board → esp32 → ESP32 Dev Module**
2. **Tools → Upload Speed → 115200** ⚠️ *Important – the default faster speed fails on this device.*
3. **Tools → Port →** choose the one containing **usbserial** (e.g. `/dev/cu.usbserial-…`)
4. Click the **Upload** button (the right-pointing arrow ➜ at the top). Wait for *Done uploading* at the bottom.

**7d. Check it's working**

Open **Tools → Serial Monitor** and set the speed in its bottom-right corner to **115200 baud**. Within a few seconds you should see:

```
WiFi connected, ATOM IP: 192.168.1.134
Connecting to MQTT 192.168.1.154:1883 as atom-... connected
home/living-room/bme280 {"temperature":22.43,"humidity":41.95,"pressure":970.53}
```

…and the light on the ATOM gives a **short green blink every 5 seconds**. 🎉

You can now unplug it from the Mac and power it from any USB phone charger – it remembers its settings.

### Step 8 – Open the dashboard

1. In your web browser, go to **http://localhost:3001**
2. Log in with username **`admin`** and password **`admin`** (click **Skip** if it asks you to choose a new password).
3. Open the menu **☰** (top left) → **Dashboards** → **Home** → **Home sensors**.

You'll see the current temperature, humidity and pressure, plus graphs that fill in over time. The page updates itself every 5 seconds.

> **From your phone or another computer** on the same WiFi, use your Mac's address instead of `localhost`, e.g. `http://192.168.1.154:3001`.

---

## Everyday use

### What the light on the sensor means

| Light | Meaning |
|---|---|
| 🔵 **Blue** | Connecting to WiFi or to your computer |
| 🟢 **Short green blink** every 5 s | All good – a reading was just sent |
| 🔴 **Red, stays on** | A problem – see [Troubleshooting](#troubleshooting). It clears by itself once things work again. |
| ⚫ **Off between blinks** | Normal |

### Starting everything again after a restart

After your Mac restarts:

1. Open **Docker Desktop** (the database and dashboard start automatically).
2. Open Terminal and start the ingestion service again:
   ```
   cd ~/home-automation/ingestion
   npm run dev
   ```
3. The sensor reconnects on its own.

### Stopping everything

- Ingestion service: click its Terminal window and press <kbd>Ctrl</kbd> + <kbd>C</kbd>.
- Everything else: `cd ~/home-automation && docker compose down`

Your saved readings are **kept** – they'll still be there next time you start.

### Adding another sensor

Repeat Steps 6 and 7 with the new ATOM Lite, giving it a **different `ROOM` name**. It appears on the dashboard automatically – use the **Room** drop-down at the top of the dashboard to choose which rooms to show.

### Looking at the raw data (optional)

Go to **http://localhost:8081**, choose **System: PostgreSQL**, and log in with:

| Field | Value |
|---|---|
| Server | `postgres` |
| Username | `home` |
| Password | `home_dev_password` |
| Database | `home` |

Click **sensor_readings → Select data** to see every saved reading.

---

## Troubleshooting

| Problem | What to try |
|---|---|
| **Light stays red straight after switching on**, Serial Monitor says *BME280 not found* | The sensor cable is loose. Unplug and firmly re-plug both ends of the Grove cable, then press the button on the ATOM or re-plug USB. |
| **Light stays blue**, Serial Monitor shows *WiFi failed* | Check the WiFi name and password in `secrets.h` (they're case-sensitive), and make sure it's a **2.4 GHz** network. Re-upload after fixing. |
| **Light turns red**, Serial Monitor shows *failed (state -2)* | The sensor can't reach your Mac. Check `MQTT_HOST` matches your Mac's current address (Step 5 – it can change after a router restart), that Docker Desktop is running, and that you clicked **Allow** for incoming connections. |
| **Upload fails** in Arduino IDE | Make sure **Upload Speed is 115200**. Try a different USB cable (it must be a data cable). Unplug and re-plug the ATOM, then upload again. |
| **No "usbserial" port** in Tools → Port | Try another USB cable or USB socket. If still missing, install the USB driver from M5Stack's website (search "M5Stack CH9102 driver"). |
| **Dashboard shows "No data"** | Is the ingestion Terminal window still open and running (Step 4)? Is the ATOM blinking green? Try changing the time range (top right) to **Last 30 minutes**. |
| **"Seconds since last reading" tile is red** | No reading for 30+ seconds. Check the sensor's light, then the ingestion Terminal window for error messages. |
| **Grafana says the password is wrong** | Reset it by running: `docker exec home-grafana grafana cli admin reset-admin-password admin` |
| **`docker: command not found`** | Docker Desktop isn't installed or isn't open. Also check your Terminal prompt ends with `%` – if it shows `#` you're inside a container; type `exit`. |
| **Ingestion shows *database write failed*** | Make sure `docker compose up -d` ran successfully (Step 3). Run `docker compose ps` – all four services should say **running**. |

---

## For developers

<details>
<summary><b>Architecture, tech stack and project layout</b> (click to expand)</summary>

### Tech stack

| Layer | Technology |
|---|---|
| Device | ESP32 (M5Stack ATOM Lite), Bosch BME280 over I2C, Arduino C++, PubSubClient |
| Messaging | MQTT via Eclipse Mosquitto 2 |
| Ingestion | Node.js, TypeScript (ESM), mqtt.js, Zod, node-postgres |
| Storage | PostgreSQL 17 |
| Visualisation | Grafana (provisioned as code), Adminer |
| Infrastructure | Docker Compose – the same stack is intended to run on a Raspberry Pi 5 |

### Services and ports

| Service | Port on host | Purpose |
|---|---|---|
| Mosquitto | 1883 | MQTT broker |
| PostgreSQL | **5433** → 5432 | Database (5433 avoids clashing with a locally installed Postgres) |
| Grafana | 3001 → 3000 | Dashboards |
| Adminer | 8081 → 8080 | Database web UI |

### MQTT contract

- Topic: `home/<room>/<sensor>`, e.g. `home/living-room/bme280` (names: `[a-z0-9-]`, max 40 chars)
- Payload: `{"temperature":22.70,"humidity":40.09,"pressure":984.88}` (°C, %RH, hPa)

### Ingestion service (`ingestion/`)

- Subscribes to `home/+/bme280` (QoS 1)
- Validates topic names and payloads with Zod, including the BME280's physical ranges; rejects and logs bad messages
- Decouples receiving from storing: readings go into an in-memory buffer and are batch-inserted
- Survives broker or database outages: auto-reconnects, retries failed writes, and drops the oldest readings only if the buffer limit is reached
- Graceful shutdown on SIGINT/SIGTERM flushes queued readings
- Configurable via environment variables: `MQTT_URL`, `MQTT_TOPIC`, `DATABASE_URL`, `MAX_BUFFER`, `RETRY_MS`, `STATS_EVERY_MS`
- `npm run simulate` publishes realistic fake readings (with occasional invalid ones) for development; options `ROOMS`, `INTERVAL_MS`, `INVALID_RATE`

### Database

`db/init.sql` creates `sensor_readings` (with an index on `room, recorded_at DESC`) the first time the Postgres container starts with an empty volume.

### Project layout

```
firmware/atom_bme280_mqtt/   ESP32 sketch (WiFi credentials in untracked secrets.h)
ingestion/                   TypeScript MQTT → PostgreSQL service + simulator
db/init.sql                  Database schema
mosquitto/                   Broker config
grafana/provisioning/        Datasource + dashboard JSON, loaded automatically
docker-compose.yml           The whole backend stack
```

### Security note

This is a home-network development setup: Mosquitto allows anonymous connections and the passwords in `docker-compose.yml` are development defaults. Add MQTT authentication and change the passwords before exposing any of it beyond your local network.

</details>

---

## Roadmap

- [x] ESP32 firmware publishing over MQTT
- [x] Docker stack: Mosquitto, PostgreSQL, Grafana
- [x] TypeScript ingestion service with validation and retry
- [x] Grafana dashboard, provisioned as code
- [ ] Custom web app (Laravel + React) with live updates and control of lights/switches
- [ ] AI features
- [ ] Run permanently on a Raspberry Pi 5 with its own screen
