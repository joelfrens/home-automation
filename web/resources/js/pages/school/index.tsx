import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Sparkles, X } from 'lucide-react';
import ChildController from '@/actions/App/Http/Controllers/School/ChildController';
import InputError from '@/components/input-error';
import { colourClass } from '@/components/school/child-colours';
import { EventCard } from '@/components/school/event-card';
import { friendlyDate } from '@/components/school/format';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/school';
import type { Child, SchoolEvent } from '@/types';

type Props = {
    events: SchoolEvent[];
    children: Child[];
    colours: string[];
};

export default function SchoolIndex({ events, children, colours }: Props) {
    // A tapped phone notification opens /school?event=12: highlight that event.
    const highlighted =
        typeof window === 'undefined'
            ? null
            : Number(new URLSearchParams(window.location.search).get('event'));

    const byDate = Object.entries(
        events.reduce<Record<string, SchoolEvent[]>>((groups, event) => {
            (groups[event.date] ??= []).push(event);

            return groups;
        }, {}),
    );

    return (
        <>
            <Head title="School" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">School & family</h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Sparkles className="size-4" /> Add from a letter
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {byDate.length === 0 && (
                            <Card>
                                <CardContent className="py-10 text-center text-muted-foreground">
                                    Nothing coming up. Add a school letter or
                                    screenshot and the AI will find the dates.
                                </CardContent>
                            </Card>
                        )}

                        {byDate.map(([date, dayEvents]) => (
                            <section key={date} className="space-y-2">
                                <h2 className="text-sm font-medium text-muted-foreground">
                                    {friendlyDate(date)}
                                </h2>
                                {dayEvents.map((event) => (
                                    <EventCard
                                        key={event.id}
                                        event={event}
                                        highlighted={event.id === highlighted}
                                    />
                                ))}
                            </section>
                        ))}
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Children</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ul className="space-y-2">
                                {children.map((child) => (
                                    <li
                                        key={child.id}
                                        className="flex items-center gap-2"
                                    >
                                        <span
                                            className={cn(
                                                'size-3 rounded-full',
                                                colourClass(child.colour),
                                            )}
                                        />
                                        <span className="flex-1">
                                            {child.name}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${child.name}`}
                                            onClick={() =>
                                                router.delete(
                                                    ChildController.destroy.url(
                                                        child.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>

                            <Form
                                {...ChildController.store.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="flex gap-2">
                                            <Input
                                                name="name"
                                                placeholder="Name"
                                                aria-label="Child's name"
                                                required
                                            />
                                            <select
                                                name="colour"
                                                aria-label="Colour"
                                                defaultValue={
                                                    colours[
                                                        children.length %
                                                            colours.length
                                                    ]
                                                }
                                                className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                            >
                                                {colours.map((colour) => (
                                                    <option
                                                        key={colour}
                                                        value={colour}
                                                    >
                                                        {colour}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                size="icon"
                                                aria-label="Add child"
                                                disabled={processing}
                                            >
                                                <Plus className="size-4" />
                                            </Button>
                                        </div>
                                        <InputError message={errors.name} />
                                    </>
                                )}
                            </Form>
                            <p className="text-xs text-muted-foreground">
                                Names help the AI match letters to the right
                                child.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SchoolIndex.layout = {
    breadcrumbs: [{ title: 'School', href: index() }],
};
