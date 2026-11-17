import { z } from "zod";

// What a valid MQTT message from a BME280 sensor must look like.
// Ranges are the BME280's physical operating limits.
export const ReadingPayload = z.object({
  temperature: z.number().min(-40).max(85),
  humidity: z.number().min(0).max(100),
  pressure: z.number().min(300).max(1100),
});

export type ReadingPayload = z.infer<typeof ReadingPayload>;

export interface Reading extends ReadingPayload {
  room: string;
  sensor: string;
  recordedAt: Date;
}

// Topics look like: home/<room>/<sensor>   e.g. home/living-room/bme280
const NAME = /^[a-z0-9-]{1,40}$/;

export function parseTopic(topic: string): { room: string; sensor: string } | null {
  const parts = topic.split("/");
  if (parts.length !== 3 || parts[0] !== "home") return null;
  const [, room, sensor] = parts;
  if (!NAME.test(room) || !NAME.test(sensor)) return null;
  return { room, sensor };
}
