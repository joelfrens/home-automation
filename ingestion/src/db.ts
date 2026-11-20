import pg from "pg";
import { config } from "./config.js";
import type { MeasurementRow } from "./measurements.js";

export const pool = new pg.Pool({ connectionString: config.databaseUrl, max: 5 });

// Without this, a dropped idle connection would crash the process.
pool.on("error", (err) => console.error("[db] idle connection error:", err.message));

// Inserts a batch of measurement rows in a single statement.
// (The measurements table is created by Laravel: php artisan migrate.)
export async function insertMeasurements(rows: MeasurementRow[]): Promise<void> {
  if (rows.length === 0) return;

  const values: unknown[] = [];
  const placeholders = rows.map((r, i) => {
    const b = i * 6;
    values.push(r.recordedAt, r.room, r.sensor, r.metric, r.value, r.unit);
    return `($${b + 1}, $${b + 2}, $${b + 3}, $${b + 4}, $${b + 5}, $${b + 6})`;
  });

  await pool.query(
    `INSERT INTO measurements (recorded_at, room, sensor, metric, value, unit)
     VALUES ${placeholders.join(", ")}`,
    values,
  );
}
