import { useEffect, useState } from 'react';

/**
 * The current time, re-rendering every `intervalMs` so "12 s ago" labels keep counting.
 * Returns null until the page is running in the browser (avoids a server/browser mismatch
 * when the page is first rendered on the server).
 */
export function useNow(intervalMs = 1000): number | null {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        setNow(Date.now());
        const timer = setInterval(() => setNow(Date.now()), intervalMs);

        return () => clearInterval(timer);
    }, [intervalMs]);

    return now;
}

/** "8 s ago", "4 min ago", "2 h ago", "3 d ago" */
export function timeAgo(iso: string, now: number): string {
    const seconds = Math.max(0, Math.round((now - Date.parse(iso)) / 1000));

    if (seconds < 60) {
        return `${seconds} s ago`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return `${Math.floor(seconds / 86400)} d ago`;
}
