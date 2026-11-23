import { Link } from '@inertiajs/react';
import { Bell, FileText, MapPin, Package } from 'lucide-react';
import SchoolController from '@/actions/App/Http/Controllers/School/SchoolController';
import { cn } from '@/lib/utils';
import { colourClass } from './child-colours';
import { friendlyDate, reminderLabel } from './format';
import type { SchoolEvent } from '@/types';

/** One saved event: who, when, what to bring, and its reminders. */
export function EventCard({
    event,
    highlighted = false,
    compact = false,
}: {
    event: SchoolEvent;
    highlighted?: boolean;
    compact?: boolean;
}) {
    const when = event.allDay
        ? 'All day'
        : [event.startTime, event.endTime].filter(Boolean).join('–');
    const pending = event.reminders.filter((r) => !r.sentAt && !r.skipped);

    return (
        <Link
            href={SchoolController.edit(event.id)}
            id={`event-${event.id}`}
            className={cn(
                'flex gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50',
                highlighted && 'ring-2 ring-primary',
            )}
        >
            <span
                className={cn(
                    'mt-1 w-1.5 shrink-0 self-stretch rounded-full',
                    colourClass(event.child?.colour),
                )}
                aria-hidden
            />
            <div className="min-w-0 flex-1 space-y-1">
                <p className="font-medium">
                    {event.child && (
                        <span className="text-muted-foreground">
                            {event.child.name}:{' '}
                        </span>
                    )}
                    {event.title}
                </p>
                <p className="text-sm text-muted-foreground">
                    {friendlyDate(event.date)} · {when}
                </p>
                {event.needs && (
                    <p className="flex items-center gap-1.5 text-sm">
                        <Package className="size-3.5 shrink-0" />
                        {event.needs}
                    </p>
                )}
                {!compact && event.location && (
                    <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin className="size-3.5 shrink-0" />
                        {event.location}
                    </p>
                )}
                {!compact && pending.length > 0 && (
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Bell className="size-3.5 shrink-0" />
                        {pending
                            .map((r) => reminderLabel(r.daysBefore, r.time))
                            .join(' · ')}
                    </p>
                )}
                {!compact && event.hasSource && (
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <FileText className="size-3.5 shrink-0" />
                        From a letter
                    </p>
                )}
            </div>
        </Link>
    );
}
