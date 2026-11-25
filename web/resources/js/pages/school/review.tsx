import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import SchoolController from '@/actions/App/Http/Controllers/School/SchoolController';
import InputError from '@/components/input-error';
import { errorsFor, EventFields } from '@/components/school/event-fields';
import { friendlyDate } from '@/components/school/format';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { index, review } from '@/routes/school';
import type { Child, EventInput } from '@/types';

/**
 * What the AI found in the letter. Nothing is saved yet: untick what's wrong, fix dates,
 * change reminders, then save.
 */
export default function ReviewSchoolEvents({
    proposals,
    children,
}: {
    proposals: EventInput[];
    children: Child[];
}) {
    const form = useForm<{ events: EventInput[] }>({ events: proposals });
    const [kept, setKept] = useState<boolean[]>(proposals.map(() => true));

    const save = () => {
        form.transform((data) => ({
            events: data.events.filter((_, i) => kept[i]),
        }));
        form.post(SchoolController.storeReviewed.url());
    };

    const keptCount = kept.filter(Boolean).length;

    return (
        <>
            <Head title="Check events" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        The AI found {proposals.length}{' '}
                        {proposals.length === 1 ? 'event' : 'events'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Check the dates and reminders. Untick anything you don't
                        want.
                    </p>
                </div>

                <InputError message={form.errors.events} />

                {form.data.events.map((event, i) => (
                    <Card key={i} className={kept[i] ? '' : 'opacity-50'}>
                        <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                            <Checkbox
                                id={`keep-${i}`}
                                checked={kept[i]}
                                onCheckedChange={(checked) =>
                                    setKept(
                                        kept.map((k, j) =>
                                            j === i ? checked === true : k,
                                        ),
                                    )
                                }
                                aria-label="Keep this event"
                            />
                            <CardTitle className="text-base">
                                <label htmlFor={`keep-${i}`}>
                                    {event.title || 'Untitled'} ·{' '}
                                    {event.date ? friendlyDate(event.date) : ''}
                                </label>
                            </CardTitle>
                        </CardHeader>
                        {kept[i] && (
                            <CardContent>
                                <EventFields
                                    value={event}
                                    onChange={(value) =>
                                        form.setData(
                                            'events',
                                            form.data.events.map((e, j) =>
                                                j === i ? value : e,
                                            ),
                                        )
                                    }
                                    childrenList={children}
                                    errors={errorsFor(
                                        form.errors,
                                        `events.${
                                            form.data.events
                                                .slice(0, i)
                                                .filter((_, j) => kept[j])
                                                .length
                                        }.`,
                                    )}
                                    idPrefix={`event-${i}`}
                                />
                            </CardContent>
                        )}
                    </Card>
                ))}

                <div className="sticky bottom-0 flex gap-2 border-t bg-background py-3">
                    <Button
                        onClick={save}
                        disabled={form.processing || keptCount === 0}
                    >
                        Save {keptCount} {keptCount === 1 ? 'event' : 'events'}
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={() =>
                            router.delete(SchoolController.cancelReview.url())
                        }
                    >
                        Cancel
                    </Button>
                </div>
            </div>
        </>
    );
}

ReviewSchoolEvents.layout = {
    breadcrumbs: [
        { title: 'School', href: index() },
        { title: 'Check events', href: review() },
    ],
};
