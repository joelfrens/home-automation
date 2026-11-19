<?php

namespace App\Http\Controllers;

use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Chart ranges: how far back to look, and how many seconds of readings
     * to average into one chart point (keeps every range to roughly 200–350 points).
     */
    public const RANGES = [
        '6h' => ['hours' => 6, 'bucket' => 120],     // 2-minute averages  → 180 points
        '24h' => ['hours' => 24, 'bucket' => 300],   // 5-minute averages  → 288 points
        '7d' => ['hours' => 168, 'bucket' => 1800],  // 30-minute averages → 336 points
    ];

    public const DEFAULT_RANGE = '24h';

    public function __invoke(Request $request): Response
    {
        // Unknown or missing ?range= falls back to the default instead of erroring
        $range = $request->query('range');
        $range = is_string($range) && array_key_exists($range, self::RANGES) ? $range : self::DEFAULT_RANGE;

        return Inertia::render('dashboard', [
            'range' => $range,
            'latest' => $this->latestPerRoom(),
            'history' => $this->history(self::RANGES[$range]['hours'], self::RANGES[$range]['bucket']),
            'recent' => SensorReading::query()->latest('recorded_at')->latest('id')->limit(20)->get()
                ->map(fn (SensorReading $r) => $this->present($r)),
        ]);
    }

    /** The newest reading of every room (one card per room). */
    private function latestPerRoom(): array
    {
        $newestIds = SensorReading::query()->selectRaw('max(id)')->groupBy('room');

        return SensorReading::query()
            ->whereIn('id', $newestIds)
            ->orderBy('room')
            ->get()
            ->map(fn (SensorReading $r) => $this->present($r))
            ->all();
    }

    /**
     * Average readings per room per time bucket, for the chart.
     * Returns rows like ['room' => 'kitchen', 't' => 1795113600000 (ms), 'temperature' => 21.4, ...].
     */
    private function history(int $hours, int $bucketSeconds): array
    {
        // "Which bucket does this reading fall in?" = Unix time rounded down to the bucket size.
        // PostgreSQL and SQLite (used by the tests) spell "Unix time" differently.
        $epoch = DB::connection()->getDriverName() === 'pgsql'
            ? 'extract(epoch from recorded_at)'
            : "cast(strftime('%s', recorded_at) as integer)";
        $bucket = "floor({$epoch} / {$bucketSeconds}) * {$bucketSeconds}";

        return SensorReading::query()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->selectRaw("room, {$bucket} as t")
            ->selectRaw('avg(temperature_c) as temperature, avg(humidity_pct) as humidity, avg(pressure_hpa) as pressure')
            ->groupBy('room', 't')
            ->orderBy('t')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'room' => $row->room,
                't' => (int) $row->t * 1000, // milliseconds, which is what JavaScript dates use
                'temperature' => round((float) $row->temperature, 2),
                'humidity' => round((float) $row->humidity, 2),
                'pressure' => round((float) $row->pressure, 2),
            ])
            ->all();
    }

    /** Shape of one reading as the React page receives it. */
    private function present(SensorReading $r): array
    {
        return [
            'id' => $r->id,
            'room' => $r->room,
            'sensor' => $r->sensor,
            'temperature' => round($r->temperature_c, 2),
            'humidity' => round($r->humidity_pct, 2),
            'pressure' => round($r->pressure_hpa, 2),
            'recordedAt' => $r->recorded_at->toIso8601String(), // the browser shows it in local time
        ];
    }
}
