import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatValue,
    metricIcon,
    roomLabel,
} from '@/components/readings/metric-format';
import { timeAgo } from '@/hooks/use-now';
import { cn } from '@/lib/utils';
import type { MetricDefinition, Reading, Thresholds } from '@/types';

export function RoomCard({
    reading,
    metrics,
    thresholds,
    now,
}: {
    reading: Reading;
    metrics: MetricDefinition[];
    thresholds: Thresholds;
    now: number | null;
}) {
    const ageS =
        now === null ? 0 : (now - Date.parse(reading.recordedAt)) / 1000;
    const status =
        ageS > thresholds.offlineAfterSeconds
            ? 'offline'
            : ageS > thresholds.staleAfterSeconds
              ? 'stale'
              : 'live';

    // Show the metrics this sensor actually sent, in the registry's order
    const shown = metrics.filter((m) => m.key in reading.values);

    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{roomLabel(reading.room)}</CardTitle>
                <Badge
                    variant="outline"
                    className={cn(
                        status === 'live' &&
                            'border-emerald-500/40 text-emerald-600 dark:text-emerald-400',
                        status === 'stale' &&
                            'border-amber-500/40 text-amber-600 dark:text-amber-400',
                        status === 'offline' &&
                            'border-red-500/40 text-red-600 dark:text-red-400',
                    )}
                >
                    {now === null ? '…' : timeAgo(reading.recordedAt, now)}
                </Badge>
            </CardHeader>
            <CardContent className="grid grid-cols-3 gap-2">
                {shown.map((metric) => {
                    const Icon = metricIcon(metric.key);

                    return (
                        <div key={metric.key} className="flex flex-col gap-1">
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Icon className="size-3.5" />
                                {metric.label}
                            </span>
                            <span className="text-2xl font-semibold tabular-nums">
                                {formatValue(
                                    metric.key,
                                    reading.values[metric.key],
                                )}
                                <span className="ml-0.5 text-sm font-normal text-muted-foreground">
                                    {metric.unit}
                                </span>
                            </span>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
