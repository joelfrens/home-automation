import { readFileSync } from "node:fs";
import { z } from "zod";

// ---------- Sensor type registry ----------
// Loaded from packages/sensors/sensor-types.json, the same file the Laravel sensors package
// reads, so validation here and labels/units in the dashboard always agree.

const MetricDefinition = z.object({
  label: z.string(),
  unit: z.string(),
  min: z.number(),
  max: z.number(),
});

const SensorTypesFile = z.record(
  z.string(),
  z.object({
    label: z.string(),
    metrics: z
      .record(z.string(), MetricDefinition)
      .refine((m) => Object.keys(m).length > 0, "a sensor type needs at least one metric"),
  }),
);

export type SensorTypes = z.infer<typeof SensorTypesFile>;

export function loadSensorTypes(path: string): SensorTypes {
  const json: unknown = JSON.parse(readFileSync(path, "utf8"));
  const result = SensorTypesFile.safeParse(json);
  if (!result.success) {
    throw new Error(`Invalid sensor types file ${path}: ${z.prettifyError(result.error)}`);
  }
  return result.data;
}

// ---------- Validating a message ----------

/** One validated MQTT message. values: metric → number, e.g. { temperature: 21.4, humidity: 45 } */
export interface Reading {
  room: string;
  sensor: string;
  recordedAt: Date;
  values: Record<string, number>;
}

export type Validation =
  | { ok: true; values: Record<string, number> }
  | { ok: false; reason: string };

/**
 * Builds one Zod schema per sensor type from the registry. Every metric of the type is
 * required and must be a number inside [min, max]; unknown extra fields are stripped.
 */
export function createValidator(types: SensorTypes) {
  const schemas = new Map(
    Object.entries(types).map(([sensor, type]) => [
      sensor,
      z.object(
        Object.fromEntries(
          Object.entries(type.metrics).map(([metric, d]) => [metric, z.number().min(d.min).max(d.max)]),
        ),
      ),
    ]),
  );

  return (sensor: string, payload: unknown): Validation => {
    const schema = schemas.get(sensor);
    if (!schema) {
      return { ok: false, reason: `unknown sensor type "${sensor}" (not in sensor-types.json)` };
    }

    const result = schema.safeParse(payload);
    if (!result.success) {
      return {
        ok: false,
        reason: result.error.issues.map((i) => `${i.path.join(".") || "payload"}: ${i.message}`).join("; "),
      };
    }

    return { ok: true, values: result.data as Record<string, number> };
  };
}

// ---------- Topics ----------

// Topics look like: home/<room>/<sensor>   e.g. home/living-room/bme280
const NAME = /^[a-z0-9-]{1,40}$/;

export function parseTopic(topic: string): { room: string; sensor: string } | null {
  const parts = topic.split("/");
  if (parts.length !== 3 || parts[0] !== "home") return null;
  const [, room, sensor] = parts;
  if (!NAME.test(room) || !NAME.test(sensor)) return null;
  return { room, sensor };
}
