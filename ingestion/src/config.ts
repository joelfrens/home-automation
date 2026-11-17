// All settings can be overridden with environment variables (e.g. on the Pi).
export const config = {
  mqttUrl: process.env.MQTT_URL ?? "mqtt://localhost:1883",
  mqttTopic: process.env.MQTT_TOPIC ?? "home/+/bme280",
  databaseUrl:
    process.env.DATABASE_URL ?? "postgres://home:home_dev_password@localhost:5432/home",
  maxBuffer: Number(process.env.MAX_BUFFER ?? 10_000), // readings kept while the DB is down
  retryMs: Number(process.env.RETRY_MS ?? 5_000),
  statsEveryMs: Number(process.env.STATS_EVERY_MS ?? 60_000),
};
