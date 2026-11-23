<?php

namespace Joelfrens\HomeAutomation\Reminders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Joelfrens\HomeAutomation\Reminders\Models\Event;
use Joelfrens\HomeAutomation\Reminders\Models\Reminder;

/**
 * Saves events and their reminders, and presents them as plain arrays for pages and AI tools.
 *
 * Input shape (from the review screen, the manual form, or the AI extractor):
 *   ['title' => 'Science museum trip', 'child_id' => 2, 'date' => '2026-12-04',
 *    'start_time' => '08:45' | null, 'end_time' => null, 'all_day' => false,
 *    'location' => ..., 'needs' => 'Packed lunch, £5', 'notes' => ..., 'source_path' => ...,
 *    'reminders' => [['days_before' => 7, 'time' => '19:00'], ['days_before' => 1, 'time' => '19:00']]]
 * Dates and times are household-local; everything is stored in UTC.
 *
 * @phpstan-type EventInput array{title: string, child_id?: int|null, date: string, start_time?: string|null,
 *     end_time?: string|null, all_day?: bool, location?: string|null, needs?: string|null, notes?: string|null,
 *     source_path?: string|null, reminders?: list<array{days_before: int, time: string}>}
 */
class EventPlanner
{
    public function __construct(private readonly string $timezone) {}

    /**
     * @param  EventInput  $input
     */
    public function create(array $input, ?int $createdBy = null, ?CarbonInterface $now = null): Event
    {
        return DB::transaction(function () use ($input, $createdBy, $now) {
            $event = Event::create([...$this->eventAttributes($input), 'created_by' => $createdBy]);

            $this->addReminders($event, $input['reminders'] ?? [], $now ?? CarbonImmutable::now());

            return $event;
        });
    }

    /**
     * Update an event and recompute its reminders. Reminders already sent stay as they were
     * (and are not sent again); unsent ones are replaced by the new list.
     *
     * @param  EventInput  $input
     */
    public function update(Event $event, array $input, ?CarbonInterface $now = null): Event
    {
        return DB::transaction(function () use ($event, $input, $now) {
            $event->update(array_diff_key($this->eventAttributes($input), ['source_path' => true]));

            Reminder::query()->where('event_id', $event->id)->pending()->delete();
            $alreadySent = Reminder::query()->where('event_id', $event->id)->get()
                ->map(fn (Reminder $r) => "{$r->days_before}|{$r->time}")
                ->all();

            $remaining = array_values(array_filter(
                $input['reminders'] ?? [],
                fn (array $r) => ! in_array("{$r['days_before']}|{$r['time']}", $alreadySent, true),
            ));

            $this->addReminders($event, $remaining, $now ?? CarbonImmutable::now());

            return $event->refresh();
        });
    }

    /**
     * Events from the start of today (household time) onwards, soonest first.
     *
     * @return Collection<int, Event>
     */
    public function upcoming(?int $limit = null, ?CarbonInterface $now = null): Collection
    {
        $startOfToday = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone)->startOfDay()->utc();

        return Event::query()
            ->with(['child', 'reminders'])
            ->where(fn ($q) => $q
                ->where('starts_at', '>=', $startOfToday)
                ->orWhere('ends_at', '>=', $startOfToday))
            ->orderBy('starts_at')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Plain array for pages, notifications and (later) AI tools. Dates/times are household-local.
     *
     * @return array{id: int, title: string, child: array{id: int, name: string, colour: string}|null,
     *     date: string, startTime: string|null, endTime: string|null, allDay: bool, location: string|null,
     *     needs: string|null, notes: string|null, startsAt: string, hasSource: bool,
     *     reminders: list<array{id: int, daysBefore: int, time: string, remindAt: string, sentAt: string|null, skipped: bool}>}
     */
    public function present(Event $event): array
    {
        $start = $event->starts_at->setTimezone($this->timezone);
        $end = $event->ends_at?->setTimezone($this->timezone);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'child' => $event->child ? [
                'id' => $event->child->id,
                'name' => $event->child->name,
                'colour' => $event->child->colour,
            ] : null,
            'date' => $start->toDateString(),
            'startTime' => $event->all_day ? null : $start->format('H:i'),
            'endTime' => $event->all_day ? null : $end?->format('H:i'),
            'allDay' => $event->all_day,
            'location' => $event->location,
            'needs' => $event->needs,
            'notes' => $event->notes,
            'startsAt' => $event->starts_at->toIso8601String(),
            'hasSource' => $event->source_path !== null,
            'reminders' => $event->reminders->map(fn (Reminder $r) => [
                'id' => $r->id,
                'daysBefore' => $r->days_before,
                'time' => $r->time,
                'remindAt' => $r->remind_at->toIso8601String(),
                'sentAt' => $r->sent_at?->toIso8601String(),
                'skipped' => $r->skipped_at !== null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  EventInput  $input
     * @return array<string, mixed>
     */
    private function eventAttributes(array $input): array
    {
        $allDay = (bool) ($input['all_day'] ?? false) || empty($input['start_time']);
        $date = $input['date'];

        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.($allDay ? '00:00' : $input['start_time']), $this->timezone);
        $end = ! $allDay && ! empty($input['end_time'])
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$input['end_time']}", $this->timezone)
            : null;

        return [
            'title' => $input['title'],
            'child_id' => $input['child_id'] ?? null,
            'starts_at' => $start->utc(),
            'ends_at' => $end?->utc(),
            'all_day' => $allDay,
            'location' => $input['location'] ?? null,
            'needs' => $input['needs'] ?? null,
            'notes' => $input['notes'] ?? null,
            'source_path' => $input['source_path'] ?? null,
        ];
    }

    /**
     * Reminders whose moment has already passed when the event is saved are not created:
     * a "7 days before" for a trip in 3 days would otherwise ping everyone immediately.
     *
     * @param  list<array{days_before: int, time: string}>  $reminders
     */
    private function addReminders(Event $event, array $reminders, CarbonInterface $now): void
    {
        $date = $event->localStart()->toDateString();

        collect($reminders)
            ->unique(fn (array $r) => "{$r['days_before']}|{$r['time']}")
            ->map(fn (array $r) => [
                'days_before' => (int) $r['days_before'],
                'time' => $r['time'],
                'remind_at' => ReminderTime::for($date, (int) $r['days_before'], $r['time'], $this->timezone),
            ])
            ->filter(fn (array $r) => $r['remind_at']->greaterThan($now))
            ->each(fn (array $r) => $event->reminders()->create($r));
    }
}
