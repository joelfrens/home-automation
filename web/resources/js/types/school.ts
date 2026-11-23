/** A child events can belong to (packages/reminders Child). */
export type Child = {
    id: number;
    name: string;
    colour: string;
};

/** "N days before at HH:MM" (days_before 0 = same day). */
export type ReminderInput = {
    days_before: number;
    time: string;
};

/**
 * One event as the forms send it (and as the AI proposes it). Same shape as the
 * reminders package's EventPlanner input; dates/times are household-local.
 */
export type EventInput = {
    title: string;
    child_id: number | null;
    date: string; // YYYY-MM-DD
    start_time: string | null; // HH:MM
    end_time: string | null;
    all_day: boolean;
    location: string | null;
    needs: string | null;
    notes: string | null;
    reminders: ReminderInput[];
};

/** A saved event as pages receive it (EventPlanner::present()). */
export type SchoolEvent = {
    id: number;
    title: string;
    child: Child | null;
    date: string;
    startTime: string | null;
    endTime: string | null;
    allDay: boolean;
    location: string | null;
    needs: string | null;
    notes: string | null;
    startsAt: string;
    hasSource: boolean;
    reminders: {
        id: number;
        daysBefore: number;
        time: string;
        remindAt: string;
        sentAt: string | null;
        skipped: boolean;
    }[];
};
