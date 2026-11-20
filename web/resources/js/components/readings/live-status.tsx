import type { ConnectionStatus } from '@laravel/echo-react';
import { cn } from '@/lib/utils';

const LABELS: Record<ConnectionStatus, string> = {
    connected: 'Live',
    connecting: 'Connecting…',
    reconnecting: 'Reconnecting…',
    disconnected: 'Offline, refreshing every 10 s',
    failed: 'Offline, refreshing every 10 s',
};

/** Shows whether readings arrive live (WebSocket to Reverb) or by polling. */
export function LiveStatus({ status }: { status: ConnectionStatus }) {
    const live = status === 'connected';
    const trying = status === 'connecting' || status === 'reconnecting';

    return (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <span className="relative flex size-2.5">
                {live && (
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-60" />
                )}
                <span
                    className={cn(
                        'relative inline-flex size-2.5 rounded-full',
                        live
                            ? 'bg-emerald-500'
                            : trying
                              ? 'bg-amber-500'
                              : 'bg-red-500',
                    )}
                />
            </span>
            {LABELS[status]}
        </div>
    );
}
