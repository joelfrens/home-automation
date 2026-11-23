/**
 * Child colours (same names as the reminders package's Child::COLOURS).
 * Full class names written out so Tailwind includes them in the build.
 */
export const CHILD_COLOURS = {
    sky: 'bg-sky-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    violet: 'bg-violet-500',
    slate: 'bg-slate-500',
} as const;

export type ChildColour = keyof typeof CHILD_COLOURS;

export function colourClass(colour: string | undefined | null): string {
    return (
        CHILD_COLOURS[(colour ?? 'slate') as ChildColour] ?? CHILD_COLOURS.slate
    );
}
