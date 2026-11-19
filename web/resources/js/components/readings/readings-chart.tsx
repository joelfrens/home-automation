import { useMemo } from 'react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { roomLabel } from '@/components/readings/room-card';
import type { HistoryPoint, Metric, Range } from '@/types';

export const METRICS: Record<Metric, { label: string; unit: string }> = {
    temperature: { label: 'Temperature', unit: '°C' },
    humidity: { label: 'Humidity', unit: '%' },
    pressure: { label: 'Pressure', unit: 'hPa' },
};

// Theme colours defined in resources/css/app.css (they adapt to light/dark mode)
const COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

/**
 * Recharts wants one object per point in time with one key per line:
 *   [{ t: 1795113600000, kitchen: 21.4, bedroom: 19.8 }, ...]
 * The server sends one row per room per time bucket, so regroup ("pivot") them.
 */
function pivot(history: HistoryPoint[], metric: Metric) {
    const byTime = new Map<number, Record<string, number>>();

    for (const point of history) {
        const row = byTime.get(point.t) ?? { t: point.t };
        row[point.room] = point[metric];
        byTime.set(point.t, row);
    }

    return [...byTime.values()].sort((a, b) => a.t - b.t);
}

export function ReadingsChart({
    history,
    metric,
    range,
}: {
    history: HistoryPoint[];
    metric: Metric;
    range: Range;
}) {
    const data = useMemo(() => pivot(history, metric), [history, metric]);
    const rooms = useMemo(
        () => [...new Set(history.map((p) => p.room))].sort(),
        [history],
    );
    const { unit } = METRICS[metric];

    const formatTick = (t: number) =>
        new Date(t).toLocaleString(undefined, {
            ...(range === '7d' ? { weekday: 'short' } : {}),
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <ResponsiveContainer width="100%" height={320}>
            <LineChart
                data={data}
                margin={{ top: 8, right: 16, bottom: 0, left: 0 }}
            >
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                <XAxis
                    dataKey="t"
                    type="number"
                    scale="time"
                    domain={['dataMin', 'dataMax']}
                    tickFormatter={formatTick}
                    tick={{ fontSize: 12, fill: 'currentColor' }}
                    className="text-muted-foreground"
                    minTickGap={40}
                />
                <YAxis
                    domain={['auto', 'auto']}
                    tickFormatter={(v: number) => `${v}${unit === '%' ? '%' : ''}`}
                    tick={{ fontSize: 12, fill: 'currentColor' }}
                    className="text-muted-foreground"
                    width={48}
                />
                <Tooltip
                    labelFormatter={(t) =>
                        new Date(Number(t)).toLocaleString(undefined, {
                            weekday: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                        })
                    }
                    formatter={(value, name) => [
                        `${Number(value).toFixed(metric === 'pressure' ? 1 : 2)} ${unit}`,
                        roomLabel(String(name)),
                    ]}
                    contentStyle={{
                        background: 'var(--popover)',
                        border: '1px solid var(--border)',
                        borderRadius: 8,
                        color: 'var(--popover-foreground)',
                    }}
                />
                {rooms.length > 1 && (
                    <Legend formatter={(name) => roomLabel(String(name))} />
                )}
                {rooms.map((room, i) => (
                    <Line
                        key={room}
                        dataKey={room}
                        type="monotone"
                        stroke={COLORS[i % COLORS.length]}
                        strokeWidth={2}
                        dot={false}
                        isAnimationActive={false}
                    />
                ))}
            </LineChart>
        </ResponsiveContainer>
    );
}
