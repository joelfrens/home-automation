import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { LiveStatus } from '@/components/readings/live-status';
import { ReadingsChart } from '@/components/readings/readings-chart';
import { RecentReadings } from '@/components/readings/recent-readings';
import { RoomCard } from '@/components/readings/room-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLiveReadings } from '@/hooks/use-live-readings';
import { useNow } from '@/hooks/use-now';
import { dashboard } from '@/routes';
import type {
    HistoryPoint,
    MetricDefinition,
    Range,
    Reading,
    RecentReading,
    Thresholds,
} from '@/types';

type Props = {
    range: Range;
    metrics: MetricDefinition[]; // from packages/sensors/sensor-types.json
    thresholds: Thresholds;
    latest: Reading[];
    history: HistoryPoint[];
    recent: RecentReading[];
};

const RANGES: Range[] = ['6h', '24h', '7d'];

export default function Dashboard({
    range,
    metrics,
    thresholds,
    latest: storedLatest,
    history,
    recent: storedRecent,
}: Props) {
    const [metricKey, setMetricKey] = useState(metrics[0]?.key ?? '');
    const metric = metrics.find((m) => m.key === metricKey) ?? metrics[0];
    const now = useNow();

    // Readings from the database, plus new ones pushed live by Reverb (see the hook for details).
    const { latest, recent, status } = useLiveReadings(
        storedLatest,
        storedRecent,
    );

    const changeRange = (value: string) => {
        if (!value) {
            return; // clicking the selected item again would un-select it
        }

        router.get(
            dashboard.url({ query: { range: value } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['range', 'history'],
            },
        );
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex justify-end">
                    <LiveStatus status={status} />
                </div>
                {latest.length === 0 ? (
                    <NoReadingsYet />
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {latest.map((reading) => (
                                <RoomCard
                                    key={`${reading.room}|${reading.sensor}`}
                                    reading={reading}
                                    metrics={metrics}
                                    thresholds={thresholds}
                                    now={now}
                                />
                            ))}
                        </div>

                        {metric && (
                            <Card>
                                <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle>
                                        {metric.label} ({metric.unit})
                                    </CardTitle>
                                    <div className="flex flex-wrap gap-2">
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            size="sm"
                                            value={metric.key}
                                            onValueChange={(v) =>
                                                v && setMetricKey(v)
                                            }
                                        >
                                            {metrics.map((m) => (
                                                <ToggleGroupItem
                                                    key={m.key}
                                                    value={m.key}
                                                    className="px-3"
                                                >
                                                    {m.label}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            size="sm"
                                            value={range}
                                            onValueChange={changeRange}
                                        >
                                            {RANGES.map((r) => (
                                                <ToggleGroupItem
                                                    key={r}
                                                    value={r}
                                                    className="px-3"
                                                >
                                                    {r}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <ReadingsChart
                                        history={history}
                                        metric={metric}
                                        range={range}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Latest readings</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <RecentReadings
                                    readings={recent}
                                    metrics={metrics}
                                />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function NoReadingsYet() {
    return (
        <Card>
            <CardContent className="py-16 text-center">
                <p className="font-medium">No readings yet</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Plug in a sensor, or run{' '}
                    <code className="rounded bg-muted px-1">
                        cd ingestion && npm run simulate
                    </code>{' '}
                    to send fake ones. New readings appear here automatically.
                </p>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
