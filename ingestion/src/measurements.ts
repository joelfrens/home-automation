import type { Reading, SensorTypes } from "./schema.js";

/** One row of the measurements table (owned by the Laravel sensors package migration). */
export interface MeasurementRow {
  recordedAt: Date;
  room: string;
  sensor: string;
  metric: string;
  value: number;
  unit: string;
}

/**
 * The database stores one row per metric ("narrow" table), so a BME280 message becomes
 * three rows (temperature, humidity, pressure) that share the same recordedAt.
 * The unit is copied from the registry so each row is self-describing (handy in Grafana).
 */
export function toMeasurementRows(readings: Reading[], types: SensorTypes): MeasurementRow[] {
  return readings.flatMap((r) =>
    Object.entries(r.values).map(([metric, value]) => ({
      recordedAt: r.recordedAt,
      room: r.room,
      sensor: r.sensor,
      metric,
      value,
      unit: types[r.sensor]?.metrics[metric]?.unit ?? "",
    })),
  );
}
