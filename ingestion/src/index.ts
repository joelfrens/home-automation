import "./env.js"; // first: loads the repo-root .env before config is read
import { connect } from "mqtt";
import { config } from "./config.js";
import { insertMeasurements, pool } from "./db.js";
import { toMeasurementRows } from "./measurements.js";
import { type Reading, createValidator, loadSensorTypes, parseTopic } from "./schema.js";
import { WriteBuffer } from "./buffer.js";

const sensorTypes = loadSensorTypes(config.sensorTypesPath);
const validate = createValidator(sensorTypes);
console.log(`[app] sensor types: ${Object.keys(sensorTypes).join(", ")} (from ${config.sensorTypesPath})`);

const buffer = new WriteBuffer<Reading>(
  (readings) => insertMeasurements(toMeasurementRows(readings, sensorTypes)),
  config.maxBuffer,
  config.retryMs,
);
let received = 0;
let rejected = 0;

const client = connect(config.mqttUrl, {
  clientId: `ingestion-${process.pid}`,
  username: config.mqttUsername,
  password: config.mqttPassword,
  reconnectPeriod: 5_000, // keep retrying if the broker goes away
});

client.on("connect", () => {
  console.log(`[mqtt] connected to ${config.mqttUrl}`);
  client.subscribe(config.mqttTopic, { qos: 1 }, (err) => {
    if (err) console.error("[mqtt] subscribe failed:", err.message);
    else console.log(`[mqtt] subscribed to ${config.mqttTopic}`);
  });
});
client.on("reconnect", () => console.log("[mqtt] reconnecting…"));
client.on("offline", () => console.warn("[mqtt] broker offline"));
client.on("error", (err) => {
  console.error("[mqtt] error:", err.message);
  if (/not authori[sz]ed|bad user name or password/i.test(err.message)) {
    console.error("[mqtt] login refused: check MQTT_INGESTION_PASSWORD in the repo-root .env");
  }
});

client.on("message", (topic, payload) => {
  received++;

  const source = parseTopic(topic);
  if (!source) {
    rejected++;
    console.warn(`[validate] ignored unexpected topic "${topic}"`);
    return;
  }

  let json: unknown;
  try {
    json = JSON.parse(payload.toString());
  } catch {
    rejected++;
    console.warn(`[validate] ${topic}: message is not valid JSON`);
    return;
  }

  const result = validate(source.sensor, json);
  if (!result.ok) {
    rejected++;
    console.warn(`[validate] ${topic}: rejected (${result.reason})`);
    return;
  }

  const metrics = sensorTypes[source.sensor].metrics;
  console.log(
    `[reading] ${source.room}/${source.sensor}: ` +
      Object.entries(result.values)
        .map(([metric, value]) => `${value.toFixed(2)} ${metrics[metric].unit}`)
        .join("  "),
  );
  buffer.push({ ...source, recordedAt: new Date(), values: result.values });
});

const statsTimer = setInterval(() => {
  console.log(
    `[stats] received=${received} stored=${buffer.stored} rejected=${rejected} ` +
      `waiting=${buffer.size} dropped=${buffer.dropped}`,
  );
}, config.statsEveryMs);

// Graceful shutdown: stop receiving, save what's queued, close connections.
let shuttingDown = false;
async function shutdown(signal: string) {
  if (shuttingDown) return;
  shuttingDown = true;
  console.log(`\n[app] ${signal} received, shutting down…`);
  clearInterval(statsTimer);
  await client.endAsync();
  await buffer.drain();
  await pool.end();
  console.log(`[app] stopped (stored ${buffer.stored} reading(s) in total)`);
  process.exit(0);
}
process.on("SIGINT", () => void shutdown("SIGINT"));
process.on("SIGTERM", () => void shutdown("SIGTERM"));
