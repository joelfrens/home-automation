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
import { formatValue, roomLabel } from '@/components/readings/metric-format';
import type { HistoryPoint, MetricDefinition, Range } from '@/types';

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
 * The server sends one row per room, metric and time bucket, so keep the chosen
 * metric and regroup ("pivot") by time.
 */
function pivot(history: HistoryPoint[], metric: string) {
    const byTime = new Map<number, Record<string, number>>();

    for (const point of history) {
        if (point.metric !== metric) {
            continue;
        }

        const row = byTime.get(point.t) ?? { t: point.t };
        row[point.room] = point.value;
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
    metric: MetricDefinition;
    range: Range;
}) {
    const data = useMemo(
        () => pivot(history, metric.key),
        [history, metric.key],
    );
    const rooms = useMemo(
        () =>
            [
                ...new Set(
                    history
                        .filter((p) => p.metric === metric.key)
                        .map((p) => p.room),
                ),
            ].sort(),
        [history, metric.key],
    );

    if (data.length === 0) {
        return (
            <p className="py-16 text-center text-sm text-muted-foreground">
                No {metric.label.toLowerCase()} readings in the last {range}.
            </p>
        );
    }

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
                <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                />
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
                        `${formatValue(metric.key, Number(value), 1)} ${metric.unit}`,
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
