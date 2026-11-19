// Shapes of the sensor data sent by DashboardController (keep the two in sync).

export type Reading = {
    id: number;
    room: string;
    sensor: string;
    temperature: number; // °C
    humidity: number; // %RH
    pressure: number; // hPa
    recordedAt: string; // ISO 8601, UTC
};

/** One chart point: the average of one room's readings over one time bucket. */
export type HistoryPoint = {
    room: string;
    t: number; // bucket start, milliseconds since 1970 (JavaScript time)
    temperature: number;
    humidity: number;
    pressure: number;
};

export type Metric = 'temperature' | 'humidity' | 'pressure';

export type Range = '6h' | '24h' | '7d';
