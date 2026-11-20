import { fileURLToPath } from "node:url";

// All settings can be overridden with environment variables (e.g. on the Pi).
export const config = {
  mqttUrl: process.env.MQTT_URL ?? "mqtt://localhost:1883",
  mqttTopic: process.env.MQTT_TOPIC ?? "home/+/+", // every room, every sensor type
  databaseUrl:
    process.env.DATABASE_URL ?? "postgres://home:home_dev_password@localhost:5433/home",
  // Shared with the Laravel sensors package (same path from src/ and dist/)
  sensorTypesPath:
    process.env.SENSOR_TYPES_PATH ??
    fileURLToPath(new URL("../../packages/sensors/sensor-types.json", import.meta.url)),
  maxBuffer: Number(process.env.MAX_BUFFER ?? 10_000), // readings kept while the DB is down
  retryMs: Number(process.env.RETRY_MS ?? 5_000),
  statsEveryMs: Number(process.env.STATS_EVERY_MS ?? 60_000),
};
