import { Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { reminderLabel } from './format';
import type { Child, EventInput, ReminderInput } from '@/types';

const selectClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 text-base shadow-xs md:text-sm dark:bg-input/30';

const DAY_OPTIONS = [0, 1, 2, 3, 7, 14];

/**
 * The fields of one event: used by "Add by hand", the AI review screen and Edit.
 * Controlled: the parent owns the value (e.g. Inertia useForm data) and gets every change.
 */
export function EventFields({
    value,
    onChange,
    childrenList,
    errors = {},
    idPrefix = 'event',
}: {
    value: EventInput;
    onChange: (value: EventInput) => void;
    childrenList: Child[];
    errors?: Partial<Record<string, string>>;
    idPrefix?: string;
}) {
    const set = <K extends keyof EventInput>(key: K, v: EventInput[K]) =>
        onChange({ ...value, [key]: v });

    const setReminder = (index: number, reminder: ReminderInput) =>
        set(
            'reminders',
            value.reminders.map((r, i) => (i === index ? reminder : r)),
        );

    const id = (name: string) => `${idPrefix}-${name}`;

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={id('title')}>What</Label>
                <Input
                    id={id('title')}
                    value={value.title}
                    onChange={(e) => set('title', e.target.value)}
                    placeholder="Science museum trip"
                    enterKeyHint="next"
                    required
                />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('child')}>Who</Label>
                    <select
                        id={id('child')}
                        className={selectClass}
                        value={value.child_id ?? ''}
                        onChange={(e) =>
                            set(
                                'child_id',
                                e.target.value ? Number(e.target.value) : null,
                            )
                        }
                    >
                        <option value="">Everyone / not sure</option>
                        {childrenList.map((child) => (
                            <option key={child.id} value={child.id}>
                                {child.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.child_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('date')}>Date</Label>
                    <Input
                        id={id('date')}
                        type="date"
                        value={value.date}
                        onChange={(e) => set('date', e.target.value)}
                        required
                    />
                    <InputError message={errors.date} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id={id('all-day')}
                    checked={value.all_day}
                    onCheckedChange={(checked) =>
                        onChange({
                            ...value,
                            all_day: checked === true,
                            start_time:
                                checked === true ? null : value.start_time,
                            end_time: checked === true ? null : value.end_time,
                        })
                    }
                />
                <Label htmlFor={id('all-day')}>All day</Label>
            </div>

            {!value.all_day && (
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor={id('start')}>Starts</Label>
                        <Input
                            id={id('start')}
                            type="time"
                            value={value.start_time ?? ''}
                            onChange={(e) =>
                                set('start_time', e.target.value || null)
                            }
                        />
                        <InputError message={errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={id('end')}>Ends (optional)</Label>
                        <Input
                            id={id('end')}
                            type="time"
                            value={value.end_time ?? ''}
                            onChange={(e) =>
                                set('end_time', e.target.value || null)
                            }
                        />
                        <InputError message={errors.end_time} />
                    </div>
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor={id('needs')}>Bring / wear / pay</Label>
                <Input
                    id={id('needs')}
                    value={value.needs ?? ''}
                    onChange={(e) => set('needs', e.target.value || null)}
                    placeholder="PE kit, packed lunch, £5"
                />
                <InputError message={errors.needs} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('location')}>Where (optional)</Label>
                    <Input
                        id={id('location')}
                        value={value.location ?? ''}
                        onChange={(e) =>
                            set('location', e.target.value || null)
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={id('notes')}>Notes (optional)</Label>
                    <Input
                        id={id('notes')}
                        value={value.notes ?? ''}
                        onChange={(e) => set('notes', e.target.value || null)}
                    />
                </div>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    Reminders on everyone's phone
                </legend>

                {value.reminders.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No reminders.
                    </p>
                )}

                {value.reminders.map((reminder, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <select
                            aria-label="Days before"
                            className={selectClass}
                            value={reminder.days_before}
                            onChange={(e) =>
                                setReminder(index, {
                                    ...reminder,
                                    days_before: Number(e.target.value),
                                })
                            }
                        >
                            {[
                                ...new Set([
                                    ...DAY_OPTIONS,
                                    reminder.days_before,
                                ]),
                            ]
                                .sort((a, b) => a - b)
                                .map((days) => (
                                    <option key={days} value={days}>
                                        {reminderLabel(days, '').replace(
                                            / at $/,
                                            '',
                                        )}
                                    </option>
                                ))}
                        </select>
                        <Input
                            aria-label="Time"
                            type="time"
                            className="w-32 shrink-0"
                            value={reminder.time}
                            onChange={(e) =>
                                setReminder(index, {
                                    ...reminder,
                                    time: e.target.value,
                                })
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove reminder"
                            onClick={() =>
                                set(
                                    'reminders',
                                    value.reminders.filter(
                                        (_, i) => i !== index,
                                    ),
                                )
                            }
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                ))}

                {value.reminders.length < 6 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        onClick={() =>
                            set('reminders', [
                                ...value.reminders,
                                { days_before: 1, time: '19:00' },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Add reminder
                    </Button>
                )}
                <InputError message={errors.reminders} />
            </fieldset>
        </div>
    );
}

/** A new, empty event for "Add by hand": tomorrow, all day, reminder the evening before. */
export function blankEvent(): EventInput {
    const tomorrow = new Date(Date.now() + 86_400_000);
    const pad = (n: number) => String(n).padStart(2, '0');

    return {
        title: '',
        child_id: null,
        date: `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`,
        start_time: null,
        end_time: null,
        all_day: true,
        location: null,
        needs: null,
        notes: null,
        reminders: [{ days_before: 1, time: '19:00' }],
    };
}

/**
 * Inertia returns errors with flat keys like "events.2.title" or "reminders.0.time".
 * Pick the ones for one event and strip the prefix.
 */
export function errorsFor(
    errors: Partial<Record<string, string>>,
    prefix: string,
): Partial<Record<string, string>> {
    const picked: Partial<Record<string, string>> = {};

    for (const [key, message] of Object.entries(errors)) {
        if (!key.startsWith(prefix)) {
            continue;
        }

        const field = key.slice(prefix.length);
        // reminders.0.time -> show under "reminders"
        picked[field.startsWith('reminders.') ? 'reminders' : field] = message;
    }

    return picked;
}
