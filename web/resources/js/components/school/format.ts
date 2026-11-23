/** Helpers to show household-local dates ("2026-12-04") in a friendly way. */

const parseDate = (date: string) => {
    const [y, m, d] = date.split('-').map(Number);

    return new Date(y, m - 1, d);
};

const todayString = () => {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

/** "Today", "Tomorrow", or "Fri 4 Dec". */
export function friendlyDate(date: string): string {
    const days = Math.round(
        (parseDate(date).getTime() - parseDate(todayString()).getTime()) /
            86_400_000,
    );

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    return parseDate(date).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/** "The evening before at 19:00", "Same day at 07:30", "7 days before at 19:00". */
export function reminderLabel(daysBefore: number, time: string): string {
    if (daysBefore === 0) {
        return `Same day at ${time}`;
    }

    if (daysBefore === 1) {
        return `Day before at ${time}`;
    }

    return `${daysBefore} days before at ${time}`;
}
