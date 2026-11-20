// Shapes of the sensor data sent by DashboardController, produced by the
// joelfrens/home-automation-sensors package (packages/sensors). Keep them in sync.

/** A metric from sensor-types.json, e.g. { key: 'humidity', label: 'Humidity', unit: '%' } */
export type MetricDefinition = {
    key: string;
    label: string;
    unit: string;
};

/** Everything one sensor sent in one message. */
export type Reading = {
    room: string;
    sensor: string;
    recordedAt: string; // ISO 8601
    values: Record<string, number>; // metric key → value, e.g. { temperature: 21.4 }
};

export type RecentReading = Reading & { id: string };

/** One chart point: the average of one room's metric over one time bucket. */
export type HistoryPoint = {
    room: string;
    metric: string;
    t: number; // bucket start, milliseconds since 1970 (JavaScript time)
    value: number;
};

export type Thresholds = {
    staleAfterSeconds: number;
    offlineAfterSeconds: number;
};

export type Range = '6h' | '24h' | '7d';
