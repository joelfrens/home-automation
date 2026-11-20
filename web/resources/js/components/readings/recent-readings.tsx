import { formatValue, roomLabel } from '@/components/readings/metric-format';
import type { MetricDefinition, RecentReading } from '@/types';

export function RecentReadings({
    readings,
    metrics,
}: {
    readings: RecentReading[];
    metrics: MetricDefinition[];
}) {
    // One column per metric that appears in these readings
    const columns = metrics.filter((m) =>
        readings.some((r) => m.key in r.values),
    );

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="py-2 pr-4 font-medium">Time</th>
                        <th className="py-2 pr-4 font-medium">Room</th>
                        {columns.map((m) => (
                            <th
                                key={m.key}
                                className="py-2 pr-4 text-right font-medium last:pr-0"
                            >
                                {m.label} {m.unit}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="tabular-nums">
                    {readings.map((r) => (
                        <tr key={r.id} className="border-b last:border-0">
                            <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                {new Date(r.recordedAt).toLocaleString(
                                    undefined,
                                    {
                                        day: '2-digit',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                    },
                                )}
                            </td>
                            <td className="py-2 pr-4">{roomLabel(r.room)}</td>
                            {columns.map((m) => (
                                <td
                                    key={m.key}
                                    className="py-2 pr-4 text-right last:pr-0"
                                >
                                    {m.key in r.values
                                        ? formatValue(m.key, r.values[m.key], 1)
                                        : '–'}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
