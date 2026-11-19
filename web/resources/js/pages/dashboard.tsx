import { Head, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { METRICS, ReadingsChart } from '@/components/readings/readings-chart';
import { RecentReadings } from '@/components/readings/recent-readings';
import { RoomCard } from '@/components/readings/room-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useNow } from '@/hooks/use-now';
import { dashboard } from '@/routes';
import type { HistoryPoint, Metric, Range, Reading } from '@/types';

type Props = {
    range: Range;
    latest: Reading[];
    history: HistoryPoint[];
    recent: Reading[];
};

const RANGES: Range[] = ['6h', '24h', '7d'];

export default function Dashboard({ range, latest, history, recent }: Props) {
    const [metric, setMetric] = useState<Metric>('temperature');
    const now = useNow();

    // Re-fetch the readings from Laravel every 10 s (only these props, not the whole page).
    // This is "polling". Reverb will replace it with readings pushed the moment they arrive.
    usePoll(10_000, { only: ['latest', 'history', 'recent'] });

    const changeRange = (value: string) => {
        if (!value) {
            return; // clicking the selected item again would un-select it
        }

        router.get(
            dashboard.url({ query: { range: value } }),
            {},
            { preserveState: true, preserveScroll: true, only: ['range', 'history'] },
        );
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                {latest.length === 0 ? (
                    <NoReadingsYet />
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {latest.map((reading) => (
                                <RoomCard
                                    key={reading.room}
                                    reading={reading}
                                    now={now}
                                />
                            ))}
                        </div>

                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <CardTitle>{METRICS[metric].label}</CardTitle>
                                <div className="flex flex-wrap gap-2">
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        value={metric}
                                        onValueChange={(v) =>
                                            v && setMetric(v as Metric)
                                        }
                                    >
                                        {(Object.keys(METRICS) as Metric[]).map(
                                            (m) => (
                                                <ToggleGroupItem
                                                    key={m}
                                                    value={m}
                                                    className="px-3"
                                                >
                                                    {METRICS[m].label}
                                                </ToggleGroupItem>
                                            ),
                                        )}
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
                                {history.length === 0 ? (
                                    <p className="py-16 text-center text-sm text-muted-foreground">
                                        No readings in the last {range}.
                                    </p>
                                ) : (
                                    <ReadingsChart
                                        history={history}
                                        metric={metric}
                                        range={range}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Latest readings</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <RecentReadings readings={recent} />
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
                    to send fake ones. This page refreshes every 10 seconds.
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
