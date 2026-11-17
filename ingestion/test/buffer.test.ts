import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { WriteBuffer } from "../src/buffer.js";
import type { Reading } from "../src/schema.js";

// A reading whose temperature doubles as an ID, so tests can check order easily.
const reading = (id: number): Reading => ({
  room: "test-room",
  sensor: "bme280",
  temperature: id,
  humidity: 40,
  pressure: 1000,
  recordedAt: new Date(0),
});
const ids = (rows: Reading[]) => rows.map((r) => r.temperature);

// A fake database write we can pause, then let succeed or fail, to simulate a slow database.
function deferredWriter() {
  const pending: Array<{ resolve: () => void; reject: (e: Error) => void }> = [];
  const write = vi.fn(
    (_rows: Reading[]) => new Promise<void>((resolve, reject) => pending.push({ resolve, reject })),
  );
  return {
    write,
    releaseNext: () => pending.shift()?.resolve(),
    failNext: (msg = "db down") => pending.shift()?.reject(new Error(msg)),
  };
}

// Lets queued promises and (fake) timers run.
const tick = (ms = 0) => vi.advanceTimersByTimeAsync(ms);

const RETRY_MS = 5_000;

beforeEach(() => {
  vi.useFakeTimers();
  // The buffer logs failures on purpose; keep test output clean.
  vi.spyOn(console, "error").mockImplementation(() => {});
  vi.spyOn(console, "warn").mockImplementation(() => {});
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe("WriteBuffer – normal operation", () => {
  it("writes a pushed reading to the database straight away", async () => {
    const write = vi.fn().mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();

    expect(write).toHaveBeenCalledTimes(1);
    expect(ids(write.mock.calls[0][0])).toEqual([1]);
    expect(buffer.stored).toBe(1);
    expect(buffer.size).toBe(0);
  });

  it("groups readings that arrive during a slow write into one batch", async () => {
    const db = deferredWriter();
    const buffer = new WriteBuffer(db.write, 100, RETRY_MS);

    buffer.push(reading(1)); // starts writing [1]
    buffer.push(reading(2)); // these three wait…
    buffer.push(reading(3));
    buffer.push(reading(4));
    expect(db.write).toHaveBeenCalledTimes(1);

    db.releaseNext(); // first write finishes
    await tick();

    expect(db.write).toHaveBeenCalledTimes(2);
    expect(ids(db.write.mock.calls[1][0])).toEqual([2, 3, 4]); // …then go in one INSERT
  });

  it("never sends more than batchSize readings in one write", async () => {
    const db = deferredWriter();
    const buffer = new WriteBuffer(db.write, 100, RETRY_MS, 2);

    for (let i = 1; i <= 5; i++) buffer.push(reading(i));
    for (let i = 0; i < 3; i++) {
      db.releaseNext();
      await tick();
    }

    expect(db.write.mock.calls.map(([rows]) => ids(rows))).toEqual([[1], [2, 3], [4, 5]]);
  });
});

describe("WriteBuffer – database outage", () => {
  it("keeps readings queued when a write fails", async () => {
    const write = vi.fn().mockRejectedValue(new Error("connection refused"));
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();

    expect(buffer.size).toBe(1);
    expect(buffer.stored).toBe(0);
    expect(console.error).toHaveBeenCalledWith(expect.stringContaining("connection refused"));
  });

  it("waits retryMs before trying again, then stores the reading", async () => {
    const write = vi
      .fn()
      .mockRejectedValueOnce(new Error("db down"))
      .mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();
    expect(write).toHaveBeenCalledTimes(1);

    await tick(RETRY_MS - 1); // not yet
    expect(write).toHaveBeenCalledTimes(1);

    await tick(1); // retry fires
    expect(write).toHaveBeenCalledTimes(2);
    expect(ids(write.mock.calls[1][0])).toEqual([1]);
    expect(buffer.stored).toBe(1);
    expect(buffer.size).toBe(0);
  });

  it("does not hammer the database while waiting to retry", async () => {
    const write = vi.fn().mockRejectedValueOnce(new Error("db down")).mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();
    buffer.push(reading(2)); // arrives during the retry wait
    buffer.push(reading(3));
    await tick();

    expect(write).toHaveBeenCalledTimes(1);
    expect(buffer.size).toBe(3);
  });

  it("puts a failed batch back at the front, keeping the original order", async () => {
    const db = deferredWriter();
    const buffer = new WriteBuffer(db.write, 100, RETRY_MS);

    buffer.push(reading(1)); // write of [1] in flight…
    buffer.push(reading(2)); // …while newer readings arrive
    buffer.push(reading(3));
    db.failNext(); // [1] fails and must go back in FRONT of [2, 3]
    await tick();

    expect(db.write).toHaveBeenCalledTimes(1);
    await tick(RETRY_MS);
    expect(ids(db.write.mock.calls[1][0])).toEqual([1, 2, 3]);
  });

  it("recovers after several failed retries in a row", async () => {
    const write = vi
      .fn()
      .mockRejectedValueOnce(new Error("db down"))
      .mockRejectedValueOnce(new Error("db down"))
      .mockRejectedValueOnce(new Error("db down"))
      .mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();
    await tick(RETRY_MS * 3);

    expect(write).toHaveBeenCalledTimes(4);
    expect(buffer.stored).toBe(1);
  });
});

describe("WriteBuffer – full buffer", () => {
  it("drops the oldest readings first and counts them", async () => {
    const write = vi.fn().mockRejectedValue(new Error("db down"));
    const buffer = new WriteBuffer(write, 3, RETRY_MS);

    buffer.push(reading(1));
    await tick(); // fails → retry pending
    for (let i = 2; i <= 5; i++) buffer.push(reading(i));

    expect(buffer.size).toBe(3);
    expect(buffer.dropped).toBe(2);
    expect(console.warn).toHaveBeenCalledWith(expect.stringContaining("dropped"));

    write.mockResolvedValue(undefined); // database comes back
    await tick(RETRY_MS);

    expect(ids(write.mock.calls.at(-1)![0])).toEqual([3, 4, 5]); // newest kept
    expect(buffer.stored).toBe(3);
  });
});

describe("WriteBuffer – drain (graceful shutdown)", () => {
  it("does nothing when the queue is empty", async () => {
    const write = vi.fn().mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    await buffer.drain();

    expect(write).not.toHaveBeenCalled();
  });

  it("saves queued readings immediately instead of waiting for the retry timer", async () => {
    const write = vi.fn().mockRejectedValueOnce(new Error("db down")).mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick(); // fails → retry pending
    buffer.push(reading(2));

    await buffer.drain();

    expect(buffer.stored).toBe(2);
    expect(buffer.size).toBe(0);

    await tick(RETRY_MS); // the cancelled retry must not fire
    expect(write).toHaveBeenCalledTimes(2);
  });

  it("writes in batches of batchSize", async () => {
    const write = vi.fn().mockRejectedValueOnce(new Error("db down")).mockResolvedValue(undefined);
    const buffer = new WriteBuffer(write, 100, RETRY_MS, 2);

    buffer.push(reading(1));
    await tick();
    for (let i = 2; i <= 5; i++) buffer.push(reading(i));

    await buffer.drain();

    expect(write.mock.calls.slice(1).map(([rows]) => ids(rows))).toEqual([[1, 2], [3, 4], [5]]);
  });

  it("waits for a write that is already in progress", async () => {
    const db = deferredWriter();
    const buffer = new WriteBuffer(db.write, 100, RETRY_MS);

    buffer.push(reading(1)); // write in flight
    let drained = false;
    const draining = buffer.drain().then(() => (drained = true));

    await tick(200);
    expect(drained).toBe(false);

    db.releaseNext();
    await tick(100);
    await draining;
    expect(drained).toBe(true);
    expect(buffer.stored).toBe(1);
  });

  it("logs instead of throwing if the database is still down", async () => {
    const write = vi.fn().mockRejectedValue(new Error("db down"));
    const buffer = new WriteBuffer(write, 100, RETRY_MS);

    buffer.push(reading(1));
    await tick();

    await expect(buffer.drain()).resolves.toBeUndefined();
    expect(console.error).toHaveBeenLastCalledWith(
      expect.stringContaining("could not save remaining readings"),
    );
  });
});
