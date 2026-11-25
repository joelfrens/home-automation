import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/school';
import { EventCard } from './event-card';
import type { SchoolEvent } from '@/types';

/** Dashboard card: the next few school & family events. */
export function ComingUp({ events }: { events: SchoolEvent[] }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle>Coming up</CardTitle>
                <Link
                    href={index()}
                    className="text-sm text-muted-foreground hover:underline"
                >
                    All events
                </Link>
            </CardHeader>
            <CardContent className="space-y-2">
                {events.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing coming up.
                    </p>
                ) : (
                    events.map((event) => (
                        <EventCard key={event.id} event={event} compact />
                    ))
                )}
            </CardContent>
        </Card>
    );
}
