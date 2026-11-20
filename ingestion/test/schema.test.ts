import { mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { describe, expect, it } from "vitest";
import { config } from "../src/config.js";
import { toMeasurementRows } from "../src/measurements.js";
import { createValidator, loadSensorTypes, parseTopic } from "../src/schema.js";

// The real registry shared with the Laravel sensors package
const types = loadSensorTypes(config.sensorTypesPath);
const validate = createValidator(types);

describe("parseTopic", () => {
  it("extracts room and sensor from a valid topic", () => {
    expect(parseTopic("home/living-room/bme280")).toEqual({ room: "living-room", sensor: "bme280" });
  });

  it("accepts lowercase letters, numbers and dashes", () => {
    expect(parseTopic("home/bedroom-2/sensor-01")).toEqual({ room: "bedroom-2", sensor: "sensor-01" });
  });

  it.each([
    ["wrong prefix", "office/kitchen/bme280"],
    ["too few segments", "home/kitchen"],
    ["too many segments", "home/kitchen/bme280/extra"],
    ["uppercase letters", "home/Kitchen/bme280"],
    ["spaces", "home/living room/bme280"],
    ["underscores", "home/living_room/bme280"],
    ["empty room", "home//bme280"],
    ["SQL-ish characters", "home/kitchen';--/bme280"],
  ])("rejects a topic with %s", (_reason, topic) => {
    expect(parseTopic(topic)).toBeNull();
  });

  it("accepts names up to 40 characters and rejects longer ones", () => {
    expect(parseTopic(`home/${"a".repeat(40)}/bme280`)).not.toBeNull();
    expect(parseTopic(`home/${"a".repeat(41)}/bme280`)).toBeNull();
  });
});

describe("sensor types registry", () => {
  const writeTypes = (json: string) => {
    const path = join(mkdtempSync(join(tmpdir(), "types-")), "sensor-types.json");
    writeFileSync(path, json);
    return path;
  };

  it("loads the shared file with the BME280 and its units", () => {
    expect(types.bme280.metrics.humidity).toEqual({ label: "Humidity", unit: "%", min: 0, max: 100 });
  });

  it("rejects a metric without a unit", () => {
    const path = writeTypes('{"x":{"label":"X","metrics":{"t":{"label":"T","min":0,"max":1}}}}');
    expect(() => loadSensorTypes(path)).toThrow(/Invalid sensor types file/);
  });

  it("rejects a sensor type with no metrics", () => {
    const path = writeTypes('{"x":{"label":"X","metrics":{}}}');
    expect(() => loadSensorTypes(path)).toThrow(/at least one metric/);
  });
});

describe("validating a BME280 message", () => {
  const valid = { temperature: 22.7, humidity: 40.09, pressure: 984.88 };

  it("accepts a normal reading", () => {
    expect(validate("bme280", valid)).toEqual({ ok: true, values: valid });
  });

  it("accepts values exactly on the BME280's limits", () => {
    expect(validate("bme280", { temperature: -40, humidity: 0, pressure: 300 }).ok).toBe(true);
    expect(validate("bme280", { temperature: 85, humidity: 100, pressure: 1100 }).ok).toBe(true);
  });

  it.each([
    ["temperature too low", { ...valid, temperature: -40.1 }],
    ["temperature too high", { ...valid, temperature: 85.1 }],
    ["humidity above 100 %", { ...valid, humidity: 150 }],
    ["negative humidity", { ...valid, humidity: -1 }],
    ["pressure too low", { ...valid, pressure: 299 }],
    ["pressure too high", { ...valid, pressure: 1101 }],
  ])("rejects a reading with %s", (_reason, payload) => {
    expect(validate("bme280", payload).ok).toBe(false);
  });

  it.each([
    ["a missing field", { temperature: 22.7, humidity: 40.09 }],
    ["a number sent as text", { ...valid, temperature: "22.7" }],
    ["a null value", { ...valid, pressure: null }],
    ["an empty object", {}],
    ["an array", [22.7, 40.09, 984.88]],
    ["null", null],
  ])("rejects a payload with %s", (_reason, payload) => {
    expect(validate("bme280", payload).ok).toBe(false);
  });

  it("reports which field was wrong", () => {
    const result = validate("bme280", { ...valid, humidity: 150 });
    expect(result.ok).toBe(false);
    expect(!result.ok && result.reason).toMatch(/^humidity:/);
  });

  it("strips unknown extra fields instead of storing them", () => {
    expect(validate("bme280", { ...valid, battery: 87 })).toEqual({ ok: true, values: valid });
  });
});

describe("unknown sensor types", () => {
  it("rejects a sensor type that is not in sensor-types.json", () => {
    const result = validate("water-level", { level: 12 });
    expect(result.ok).toBe(false);
    expect(!result.ok && result.reason).toMatch(/unknown sensor type "water-level"/);
  });

  it("accepts a new type once it is added to the registry", () => {
    const withWater = createValidator({
      ...types,
      "water-level": { label: "Water level", metrics: { level: { label: "Level", unit: "cm", min: 0, max: 300 } } },
    });
    expect(withWater("water-level", { level: 12 })).toEqual({ ok: true, values: { level: 12 } });
    expect(withWater("water-level", { level: 301 }).ok).toBe(false);
  });
});

describe("toMeasurementRows", () => {
  it("turns one message into one row per metric, sharing the time, with units from the registry", () => {
    const at = new Date("2026-11-19T18:00:00Z");
    const rows = toMeasurementRows(
      [{ room: "kitchen", sensor: "bme280", recordedAt: at, values: { temperature: 21.4, humidity: 45 } }],
      types,
    );

    expect(rows).toEqual([
      { recordedAt: at, room: "kitchen", sensor: "bme280", metric: "temperature", value: 21.4, unit: "°C" },
      { recordedAt: at, room: "kitchen", sensor: "bme280", metric: "humidity", value: 45, unit: "%" },
    ]);
  });
});
