import { router } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import type { ConnectionStatus } from '@laravel/echo-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { Reading, RecentReading } from '@/types';

const RECENT_LIMIT = 20;
const ALL_READINGS = ['latest', 'history', 'recent'];

const time = (r: Reading) => Date.parse(r.recordedAt);
const cardKey = (r: Reading) => `${r.room}|${r.sensor}`;

/**
 * Live readings for the dashboard.
 *
 * The page arrives with readings from the database (Inertia props). After that, Reverb pushes
 * each new reading over a WebSocket ("ReadingReceived" on the private "readings" channel; only
 * logged-in users may listen, see routes/channels.php) and we lay it on top of those props,
 * so cards and the table update the moment a reading arrives.
 *
 * Reverb doesn't store messages: anything sent while we were disconnected is lost. So:
 *  - on reconnect, reload everything from the database (catch up);
 *  - while disconnected, fall back to polling every 10 s;
 *  - while connected, only the chart is refreshed (once a minute; its buckets are minutes long).
 */
export function useLiveReadings(latest: Reading[], recent: RecentReading[]) {
    const [pushed, setPushed] = useState<RecentReading[]>([]);
    const status: ConnectionStatus = useConnectionStatus();
    const connected = status === 'connected';

    useEcho<RecentReading>('readings', 'ReadingReceived', (reading) => {
        setPushed((current) => [reading, ...current].slice(0, RECENT_LIMIT));
    });

    // Catch up after a reconnect (not on the very first connect: the page props are fresh then).
    const hasConnectedBefore = useRef(false);
    useEffect(() => {
        if (!connected) {
            return;
        }

        if (hasConnectedBefore.current) {
            router.reload({ only: ALL_READINGS });
        }

        hasConnectedBefore.current = true;
    }, [connected]);

    useEffect(() => {
        const timer = setInterval(
            () =>
                router.reload({ only: connected ? ['history'] : ALL_READINGS }),
            connected ? 60_000 : 10_000,
        );

        return () => clearInterval(timer);
    }, [connected]);

    // Combine: a pushed reading only counts if it is newer than what the database already gave us
    // (after a reload the database has caught up, and the pushed copies simply drop out).
    const liveLatest = useMemo(() => {
        const cards = new Map(latest.map((r) => [cardKey(r), r] as const));

        for (const reading of [...pushed].reverse()) {
            const existing = cards.get(cardKey(reading));

            if (!existing || time(reading) > time(existing)) {
                cards.set(cardKey(reading), reading);
            }
        }

        return [...cards.values()].sort((a, b) =>
            cardKey(a).localeCompare(cardKey(b)),
        );
    }, [latest, pushed]);

    const liveRecent = useMemo(() => {
        const newestStored = recent.length > 0 ? time(recent[0]) : 0;
        const newer = pushed.filter((r) => time(r) > newestStored);

        return [...newer, ...recent].slice(0, RECENT_LIMIT);
    }, [recent, pushed]);

    return { latest: liveLatest, recent: liveRecent, status };
}
