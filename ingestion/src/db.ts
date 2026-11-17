import pg from "pg";
import { config } from "./config.js";
import type { Reading } from "./schema.js";

export const pool = new pg.Pool({ connectionString: config.databaseUrl, max: 5 });

// Without this, a dropped idle connection would crash the process.
pool.on("error", (err) => console.error("[db] idle connection error:", err.message));

// Inserts a batch of readings in a single statement.
export async function insertReadings(rows: Reading[]): Promise<void> {
  if (rows.length === 0) return;

  const values: unknown[] = [];
  const placeholders = rows.map((r, i) => {
    const b = i * 6;
    values.push(r.room, r.sensor, r.temperature, r.humidity, r.pressure, r.recordedAt);
    return `($${b + 1}, $${b + 2}, $${b + 3}, $${b + 4}, $${b + 5}, $${b + 6})`;
  });

  await pool.query(
    `INSERT INTO sensor_readings
       (room, sensor, temperature_c, humidity_pct, pressure_hpa, recorded_at)
     VALUES ${placeholders.join(", ")}`,
    values,
  );
}
