// Publishes fake BME280 readings so you can develop without the hardware.
//   npm run simulate
//   ROOMS=kitchen,office INVALID_RATE=0.1 npm run simulate
import { connect } from "mqtt";

const url = process.env.MQTT_URL ?? "mqtt://localhost:1883";
const rooms = (process.env.ROOMS ?? "kitchen,bedroom").split(",").map((r) => r.trim());
const intervalMs = Number(process.env.INTERVAL_MS ?? 5_000);
const invalidRate = Number(process.env.INVALID_RATE ?? 0.05); // occasionally send bad data

// Each room drifts slowly around its own baseline, like a real room.
const state = new Map(
  rooms.map((room) => [
    room,
    { t: 19 + Math.random() * 4, h: 40 + Math.random() * 15, p: 1005 + Math.random() * 10 },
  ]),
);

const drift = (v: number, step: number, min: number, max: number) =>
  Math.min(max, Math.max(min, v + (Math.random() - 0.5) * step));
const round = (v: number) => Math.round(v * 100) / 100;

const client = connect(url, { clientId: `simulator-${process.pid}` });

client.on("connect", () => {
  console.log(`[sim] connected to ${url}, publishing for: ${rooms.join(", ")}`);

  setInterval(() => {
    for (const room of rooms) {
      const s = state.get(room)!;
      s.t = drift(s.t, 0.2, 15, 28);
      s.h = drift(s.h, 0.8, 25, 75);
      s.p = drift(s.p, 0.3, 980, 1035);

      const payload =
        Math.random() < invalidRate
          ? { temperature: round(s.t), humidity: 150, pressure: round(s.p) } // invalid on purpose
          : { temperature: round(s.t), humidity: round(s.h), pressure: round(s.p) };

      client.publish(`home/${room}/bme280`, JSON.stringify(payload), { qos: 1 });
    }
  }, intervalMs);
});

client.on("error", (err) => console.error("[sim] error:", err.message));
process.on("SIGINT", () => client.end(false, {}, () => process.exit(0)));
