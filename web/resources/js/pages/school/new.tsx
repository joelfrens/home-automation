import { Head, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import SchoolController from '@/actions/App/Http/Controllers/School/SchoolController';
import InputError from '@/components/input-error';
import {
    blankEvent,
    errorsFor,
    EventFields,
} from '@/components/school/event-fields';
import { FileDrop } from '@/components/school/file-drop';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index } from '@/routes/school';
import type { Child, EventInput } from '@/types';

export default function NewSchoolEvent({ children }: { children: Child[] }) {
    // 1) Let the AI read a letter / screenshot / message
    const letter = useForm<{ file: File | null; text: string }>({
        file: null,
        text: '',
    });

    // 2) ...or type an event in by hand
    const manual = useForm<EventInput>(blankEvent());

    const readWithAi = (event: React.FormEvent) => {
        event.preventDefault();
        letter.post(SchoolController.read.url(), { forceFormData: true });
    };

    const saveManual = (event: React.FormEvent) => {
        event.preventDefault();
        manual.post(SchoolController.store.url());
    };

    return (
        <>
            <Head title="Add school event" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="size-5" /> Add from a letter
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={readWithAi} className="space-y-4">
                            <FileDrop
                                file={letter.data.file}
                                onChange={(file) =>
                                    letter.setData('file', file)
                                }
                            />
                            <InputError message={letter.errors.file} />

                            <div className="grid gap-2">
                                <Label htmlFor="text">
                                    …or paste the text of a message
                                </Label>
                                <textarea
                                    id="text"
                                    rows={4}
                                    value={letter.data.text}
                                    onChange={(e) =>
                                        letter.setData('text', e.target.value)
                                    }
                                    placeholder="Reminder: Year 3 trip to the Science Museum on Friday 4 December. Please send a packed lunch and £5…"
                                    className="w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs md:text-sm"
                                />
                                <InputError message={letter.errors.text} />
                            </div>

                            {letter.progress && (
                                <progress
                                    value={letter.progress.percentage}
                                    max="100"
                                    className="w-full"
                                />
                            )}

                            <Button
                                disabled={
                                    letter.processing ||
                                    (!letter.data.file &&
                                        !letter.data.text.trim())
                                }
                                className="w-full sm:w-auto"
                            >
                                {letter.processing ? (
                                    <>
                                        <Spinner /> Reading…
                                    </>
                                ) : (
                                    <>
                                        <Sparkles className="size-4" /> Read
                                        with AI
                                    </>
                                )}
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                The AI only suggests events. You check them
                                before anything is saved.
                            </p>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Add by hand</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={saveManual} className="space-y-4">
                            <EventFields
                                value={manual.data}
                                onChange={(value) => manual.setData(value)}
                                childrenList={children}
                                errors={errorsFor(manual.errors, '')}
                                idPrefix="manual"
                            />
                            <Button disabled={manual.processing}>
                                Save event
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

NewSchoolEvent.layout = {
    breadcrumbs: [
        { title: 'School', href: index() },
        { title: 'Add', href: create() },
    ],
};
