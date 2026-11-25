import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import SchoolController from '@/actions/App/Http/Controllers/School/SchoolController';
import { errorsFor, EventFields } from '@/components/school/event-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/school';
import type { Child, EventInput, SchoolEvent } from '@/types';

export default function EditSchoolEvent({
    event,
    children,
}: {
    event: SchoolEvent;
    children: Child[];
}) {
    // Reminders already sent stay sent; the list here is what should (still) be sent.
    const form = useForm<EventInput>({
        title: event.title,
        child_id: event.child?.id ?? null,
        date: event.date,
        start_time: event.startTime,
        end_time: event.endTime,
        all_day: event.allDay,
        location: event.location,
        needs: event.needs,
        notes: event.notes,
        reminders: event.reminders
            .filter((r) => !r.skipped)
            .map((r) => ({ days_before: r.daysBefore, time: r.time })),
    });

    const sent = event.reminders.filter((r) => r.sentAt).length;

    return (
        <>
            <Head title={`Edit ${event.title}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit event</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(SchoolController.update.url(event.id));
                            }}
                            className="space-y-4"
                        >
                            <EventFields
                                value={form.data}
                                onChange={(value) => form.setData(value)}
                                childrenList={children}
                                errors={errorsFor(form.errors, '')}
                            />
                            {sent > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {sent} reminder
                                    {sent === 1 ? ' has' : 's have'} already
                                    been sent and won't be sent again.
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button disabled={form.processing}>Save</Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                                {event.hasSource && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={SchoolController.source.url(
                                                event.id,
                                            )}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <FileText className="size-4" />{' '}
                                            Original letter
                                        </a>
                                    </Button>
                                )}
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            className="ml-auto"
                                        >
                                            Delete
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>
                                            Delete “{event.title}”?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Its reminders won't be sent.
                                        </DialogDescription>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        SchoolController.destroy.url(
                                                            event.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EditSchoolEvent.layout = {
    breadcrumbs: [{ title: 'School', href: index() }],
};
