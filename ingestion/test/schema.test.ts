import { describe, expect, it } from "vitest";
import { ReadingPayload, parseTopic } from "../src/schema.js";

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

describe("ReadingPayload", () => {
  const valid = { temperature: 22.7, humidity: 40.09, pressure: 984.88 };

  it("accepts a normal reading", () => {
    expect(ReadingPayload.safeParse(valid).success).toBe(true);
  });

  it("accepts values exactly on the BME280's limits", () => {
    expect(ReadingPayload.safeParse({ temperature: -40, humidity: 0, pressure: 300 }).success).toBe(true);
    expect(ReadingPayload.safeParse({ temperature: 85, humidity: 100, pressure: 1100 }).success).toBe(true);
  });

  it.each([
    ["temperature too low", { ...valid, temperature: -40.1 }],
    ["temperature too high", { ...valid, temperature: 85.1 }],
    ["humidity above 100 %", { ...valid, humidity: 150 }],
    ["negative humidity", { ...valid, humidity: -1 }],
    ["pressure too low", { ...valid, pressure: 299 }],
    ["pressure too high", { ...valid, pressure: 1101 }],
  ])("rejects a reading with %s", (_reason, payload) => {
    expect(ReadingPayload.safeParse(payload).success).toBe(false);
  });

  it.each([
    ["a missing field", { temperature: 22.7, humidity: 40.09 }],
    ["a number sent as text", { ...valid, temperature: "22.7" }],
    ["a null value", { ...valid, pressure: null }],
    ["an empty object", {}],
    ["an array", [22.7, 40.09, 984.88]],
    ["null", null],
  ])("rejects a payload with %s", (_reason, payload) => {
    expect(ReadingPayload.safeParse(payload).success).toBe(false);
  });

  it("reports which field was wrong", () => {
    const result = ReadingPayload.safeParse({ ...valid, humidity: 150 });
    expect(result.success).toBe(false);
    expect(result.error?.issues[0].path).toEqual(["humidity"]);
  });

  it("strips unknown extra fields instead of storing them", () => {
    const result = ReadingPayload.safeParse({ ...valid, battery: 87 });
    expect(result.success).toBe(true);
    expect(result.data).toEqual(valid);
  });
});
