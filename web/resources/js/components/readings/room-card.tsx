import { Droplets, Gauge, Thermometer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { timeAgo } from '@/hooks/use-now';
import { cn } from '@/lib/utils';
import type { Reading } from '@/types';

// A sensor sends every 5 s; same thresholds as the Grafana "seconds since last reading" panel.
const STALE_AFTER_S = 30;
const OFFLINE_AFTER_S = 300;

/** "living-room" → "Living room" */
export function roomLabel(room: string): string {
    const words = room.replace(/-/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function RoomCard({
    reading,
    now,
}: {
    reading: Reading;
    now: number | null;
}) {
    const ageS =
        now === null ? 0 : (now - Date.parse(reading.recordedAt)) / 1000;
    const status =
        ageS > OFFLINE_AFTER_S
            ? 'offline'
            : ageS > STALE_AFTER_S
              ? 'stale'
              : 'live';

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
                <Value
                    icon={Thermometer}
                    label="Temperature"
                    value={reading.temperature.toFixed(1)}
                    unit="°C"
                />
                <Value
                    icon={Droplets}
                    label="Humidity"
                    value={reading.humidity.toFixed(0)}
                    unit="%"
                />
                <Value
                    icon={Gauge}
                    label="Pressure"
                    value={reading.pressure.toFixed(0)}
                    unit="hPa"
                />
            </CardContent>
        </Card>
    );
}

function Value({
    icon: Icon,
    label,
    value,
    unit,
}: {
    icon: typeof Thermometer;
    label: string;
    value: string;
    unit: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Icon className="size-3.5" />
                {label}
            </span>
            <span className="text-2xl font-semibold tabular-nums">
                {value}
                <span className="ml-0.5 text-sm font-normal text-muted-foreground">
                    {unit}
                </span>
            </span>
        </div>
    );
}
