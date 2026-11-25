<?php

namespace Joelfrens\HomeAutomation\Reminders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Joelfrens\HomeAutomation\Reminders\Ai\ExtractEvents;
use Joelfrens\HomeAutomation\Reminders\Exceptions\ExtractionFailed;
use Joelfrens\HomeAutomation\Reminders\Models\Child;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Letter / screenshot / text in -> list of proposed events out (nothing is saved here).
 *
 * The AI's answer is checked like any other user input: impossible dates and times are
 * dropped, children are matched by name, duplicate reminders removed. What comes out can
 * go straight into the review screen and then into EventPlanner::create().
 *
 * @phpstan-type Proposal array{title: string, child_id: int|null, date: string, start_time: string|null,
 *     end_time: string|null, all_day: bool, location: string|null, needs: string|null, notes: string|null,
 *     reminders: list<array{days_before: int, time: string}>}
 */
class EventExtractor
{
    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(private readonly string $timezone) {}

    /**
     * @param  string|null  $storedPath  file on the reminders disk (image or PDF), or null
     * @param  string|null  $text  pasted message text, or null
     * @return list<Proposal>
     *
     * @throws ExtractionFailed
     */
    public function extract(?string $storedPath, ?string $text, ?CarbonInterface $now = null): array
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone);
        $children = Child::query()->orderBy('name')->get();

        try {
            $response = (new ExtractEvents)->prompt(
                $this->prompt($today, $children, $text),
                attachments: $storedPath ? [$this->attachment($storedPath)] : [],
                timeout: (int) config('reminders.ai.timeout'),
            );
        } catch (Throwable $e) {
            Log::warning('Reading the letter with AI failed: '.$e->getMessage());

            throw ExtractionFailed::aiUnavailable($e);
        }

        $events = $response instanceof StructuredAgentResponse ? ($response->structured['events'] ?? null) : null;

        if (! is_array($events)) {
            throw ExtractionFailed::unexpectedAnswer();
        }

        return collect($events)
            ->map(fn ($event) => is_array($event) ? $this->clean($event, $children, $today) : null)
            ->filter()
            ->sortBy(fn (array $e) => $e['date'].' '.($e['start_time'] ?? '00:00'))
            ->values()
            ->all();
    }

    private function prompt(CarbonImmutable $today, Collection $children, ?string $text): string
    {
        $names = $children->isEmpty() ? '(none added yet)' : $children->pluck('name')->join(', ');

        $prompt = "Today is {$today->format('l j F Y')} ({$today->toDateString()}), time zone {$this->timezone}.\n"
            ."Children: {$names}.\n\n";

        if (filled($text)) {
            $prompt .= "Message:\n<<<\n".trim($text)."\n>>>\n";
        }

        return $prompt.'Find the events.';
    }

    private function attachment(string $path): Document|Image
    {
        $disk = config('reminders.storage.disk');

        return Storage::disk($disk)->mimeType($path) === 'application/pdf'
            ? Document::fromStorage($path, $disk)
            : Image::fromStorage($path, $disk);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  Collection<int, Child>  $children
     * @return Proposal|null
     */
    private function clean(array $event, Collection $children, CarbonImmutable $today): ?array
    {
        $title = trim((string) ($event['title'] ?? ''));
        $date = (string) ($event['date'] ?? '');

        if ($title === '' || ! $this->isDate($date)) {
            return null;
        }

        // Events that are already over are of no use; keep anything from today on.
        if ($date < $today->toDateString()) {
            return null;
        }

        $start = $this->time($event['start_time'] ?? null);
        $end = $start ? $this->time($event['end_time'] ?? null) : null;
        $childName = mb_strtolower(trim((string) ($event['child'] ?? '')));
        $child = $children->first(fn (Child $c) => mb_strtolower($c->name) === $childName);

        $reminders = collect(is_array($event['reminders'] ?? null) ? $event['reminders'] : [])
            ->filter(fn ($r) => is_array($r)
                && is_int($r['days_before'] ?? null) && $r['days_before'] >= 0 && $r['days_before'] <= 60
                && $this->time($r['time'] ?? null) !== null)
            ->map(fn (array $r) => ['days_before' => $r['days_before'], 'time' => $r['time']])
            ->unique(fn (array $r) => "{$r['days_before']}|{$r['time']}")
            ->sortBy([['days_before', 'desc'], ['time', 'asc']])
            ->values()
            ->all();

        return [
            'title' => mb_substr($title, 0, 200),
            'child_id' => $child?->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'all_day' => $start === null,
            'location' => $this->text($event['location'] ?? null, 200),
            'needs' => $this->text($event['needs'] ?? null, 500),
            'notes' => $this->text($event['notes'] ?? null, 1000),
            'reminders' => $reminders,
        ];
    }

    private function isDate(string $value): bool
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->toDateString() === $value;
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::TIME, $value) ? $value : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
