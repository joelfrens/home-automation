import { Activity, Droplets, Gauge, Thermometer } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

// Display details per metric. Metrics not listed here (e.g. a future "co2" or "level")
// still work: they get a generic icon and one decimal place.
const ICONS: Record<string, LucideIcon> = {
    temperature: Thermometer,
    humidity: Droplets,
    pressure: Gauge,
};

const DECIMALS: Record<string, number> = {
    temperature: 1,
    humidity: 0,
    pressure: 0,
};

export function metricIcon(metric: string): LucideIcon {
    return ICONS[metric] ?? Activity;
}

/** Rounded for display: 21.46 → "21.5" (temperature), 45.2 → "45" (humidity) */
export function formatValue(
    metric: string,
    value: number,
    extraDecimals = 0,
): string {
    return value.toFixed((DECIMALS[metric] ?? 1) + extraDecimals);
}

/** "living-room" → "Living room" */
export function roomLabel(room: string): string {
    const words = room.replace(/-/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
