<?php

namespace Joelfrens\HomeAutomation\Sensors;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Joelfrens\HomeAutomation\Sensors\Models\Measurement;

/**
 * The questions you can ask about sensor data. Everything returns plain arrays,
 * ready to be sent to a page or an API.
 *
 * A "reading" = everything one sensor sent in one message:
 *   ['room' => 'kitchen', 'sensor' => 'bme280', 'recordedAt' => '2026-…Z',
 *    'values' => ['temperature' => 21.4, 'humidity' => 45.2, 'pressure' => 1003.1]]
 */
class SensorReadings
{
    public function __construct(
        private readonly SensorTypes $types,
        private readonly int $latestLookbackHours = 168,
    ) {}

    /**
     * The newest reading from every room + sensor (one dashboard card each), sorted by room.
     * Only looks back $latestLookbackHours, so a sensor that has been gone for longer disappears
     * and the query never has to scan years of history.
     *
     * @return list<array{room: string, sensor: string, recordedAt: string, values: array<string, float>}>
     */
    public function latestPerRoom(): array
    {
        $since = now()->subHours($this->latestLookbackHours);

        $newestIds = Measurement::query()
            ->selectRaw('max(id)')
            ->where('recorded_at', '>=', $since)
            ->groupBy('room', 'sensor', 'metric');

        $rows = Measurement::query()
            ->whereIn('id', $newestIds)
            ->orderBy('room')
            ->orderBy('sensor')
            ->get();

        return $rows
            ->groupBy(fn (Measurement $m) => "{$m->room}|{$m->sensor}")
            ->map(fn (Collection $metrics) => [
                'room' => $metrics->first()->room,
                'sensor' => $metrics->first()->sensor,
                'recordedAt' => $metrics->max('recorded_at')->toIso8601String(),
                'values' => $metrics->mapWithKeys(fn (Measurement $m) => [$m->metric => round($m->value, 2)])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Averages per room, metric and time bucket over the given range (for charts), oldest first.
     *
     * @return list<array{room: string, metric: string, t: int, value: float}>  t = bucket start in milliseconds (JavaScript time)
     */
    public function history(Range $range): array
    {
        $bucketSeconds = $range->bucketSeconds();

        // "Which bucket does this reading fall in?" = Unix time rounded down to the bucket size.
        // PostgreSQL and SQLite (used by the tests) spell "Unix time" differently.
        $epoch = DB::connection((new Measurement)->getConnectionName())->getDriverName() === 'pgsql'
            ? 'extract(epoch from recorded_at)'
            : "cast(strftime('%s', recorded_at) as integer)";
        $bucket = "floor({$epoch} / {$bucketSeconds}) * {$bucketSeconds}";

        return Measurement::query()
            ->where('recorded_at', '>=', now()->subHours($range->hours()))
            ->selectRaw("room, metric, {$bucket} as t, avg(value) as value")
            ->groupBy('room', 'metric', 't')
            ->orderBy('t')
            ->orderBy('room')
            ->orderBy('metric')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'room' => $row->room,
                'metric' => $row->metric,
                't' => (int) $row->t * 1000,
                'value' => round((float) $row->value, 2),
            ])
            ->all();
    }

    /**
     * The newest $limit readings (messages) across all rooms, newest first.
     *
     * @return list<array{id: string, room: string, sensor: string, recordedAt: string, values: array<string, float>}>
     */
    public function recent(int $limit = 20): array
    {
        // A message is stored as one row per metric, so fetch enough rows to rebuild $limit messages.
        $rows = Measurement::query()
            ->latest('recorded_at')
            ->latest('id')
            ->limit($limit * $this->types->maxMetricsPerMessage())
            ->get();

        return $rows
            ->groupBy(fn (Measurement $m) => "{$m->room}|{$m->sensor}|{$m->recorded_at->format('Y-m-d\TH:i:s.v')}")
            ->take($limit)
            ->map(fn (Collection $metrics, string $key) => [
                'id' => $key,
                'room' => $metrics->first()->room,
                'sensor' => $metrics->first()->sensor,
                'recordedAt' => $metrics->first()->recorded_at->toIso8601String(),
                'values' => $metrics->sortBy('id')->mapWithKeys(fn (Measurement $m) => [$m->metric => round($m->value, 2)])->all(),
            ])
            ->values()
            ->all();
    }
}
