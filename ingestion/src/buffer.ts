/** Saves a batch of items (e.g. inserts them into the database). */
type Writer<T> = (rows: T[]) => Promise<void>;

/**
 * Decouples receiving from storing.
 * MQTT messages are pushed in instantly; a background loop writes them to the
 * database in batches. If the database is unavailable, readings stay queued
 * and are retried, so nothing is lost during a short outage.
 * Generic: T is whatever is being stored (here: one validated MQTT message, a Reading).
 */
export class WriteBuffer<T> {
  private queue: T[] = [];
  private writing = false;
  private retryTimer?: NodeJS.Timeout;
  stored = 0;
  dropped = 0;

  constructor(
    private readonly write: Writer<T>,
    private readonly maxSize: number,
    private readonly retryMs: number,
    private readonly batchSize = 500,
  ) {}

  get size(): number {
    return this.queue.length;
  }

  push(item: T): void {
    this.queue.push(item);
    this.trim();
    void this.flush();
  }

  async flush(): Promise<void> {
    if (this.writing || this.retryTimer || this.queue.length === 0) return;
    this.writing = true;

    const batch = this.queue.splice(0, this.batchSize);
    try {
      await this.write(batch);
      this.stored += batch.length;
    } catch (err) {
      this.queue.unshift(...batch); // put them back at the front
      this.trim();
      console.error(
        `[buffer] database write failed (${(err as Error).message}); ` +
          `${this.queue.length} reading(s) waiting, retrying in ${this.retryMs / 1000}s`,
      );
      this.retryTimer = setTimeout(() => {
        this.retryTimer = undefined;
        void this.flush();
      }, this.retryMs);
      return;
    } finally {
      this.writing = false;
    }

    if (this.queue.length > 0) void this.flush();
  }

  /** Try to write everything left before shutting down. */
  async drain(): Promise<void> {
    if (this.retryTimer) {
      clearTimeout(this.retryTimer);
      this.retryTimer = undefined;
    }
    while (this.writing) await new Promise((r) => setTimeout(r, 50));
    if (this.queue.length === 0) return;
    try {
      while (this.queue.length > 0) {
        const batch = this.queue.splice(0, this.batchSize);
        await this.write(batch);
        this.stored += batch.length;
      }
    } catch (err) {
      console.error(`[buffer] could not save remaining readings: ${(err as Error).message}`);
    }
  }

  // If the database is down for a long time, drop the oldest readings
  // rather than running out of memory.
  private trim(): void {
    const excess = this.queue.length - this.maxSize;
    if (excess > 0) {
      this.queue.splice(0, excess);
      this.dropped += excess;
      console.warn(`[buffer] full, dropped ${excess} oldest reading(s)`);
    }
  }
}
