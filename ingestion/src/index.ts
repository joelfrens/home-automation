import { connect } from "mqtt";
import { config } from "./config.js";
import { insertReadings, pool } from "./db.js";
import { ReadingPayload, parseTopic } from "./schema.js";
import { WriteBuffer } from "./buffer.js";

const buffer = new WriteBuffer(insertReadings, config.maxBuffer, config.retryMs);
let received = 0;
let rejected = 0;

const client = connect(config.mqttUrl, {
  clientId: `ingestion-${process.pid}`,
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
client.on("error", (err) => console.error("[mqtt] error:", err.message));

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

  const result = ReadingPayload.safeParse(json);
  if (!result.success) {
    rejected++;
    const why = result.error.issues.map((i) => `${i.path.join(".")}: ${i.message}`).join("; ");
    console.warn(`[validate] ${topic}: rejected (${why})`);
    return;
  }

  const r = result.data;
  console.log(
    `[reading] ${source.room}: ${r.temperature.toFixed(2)} °C  ` +
      `${r.humidity.toFixed(2)} %  ${r.pressure.toFixed(2)} hPa`,
  );
  buffer.push({ ...r, ...source, recordedAt: new Date() });
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
