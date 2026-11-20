<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Joelfrens\HomeAutomation\Sensors\Range;
use Joelfrens\HomeAutomation\Sensors\SensorReadings;
use Joelfrens\HomeAutomation\Sensors\SensorTypes;

/**
 * The home dashboard. All sensor logic lives in the joelfrens/home-automation-sensors
 * package (packages/sensors); this controller only turns the request into package calls
 * and hands the results to the React page. Laravel injects SensorReadings and SensorTypes
 * automatically (dependency injection) because the package registered them.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SensorReadings $readings, SensorTypes $types): Response
    {
        $range = Range::fromQuery($request->query('range'));

        return Inertia::render('dashboard', [
            'range' => $range->value,
            'metrics' => collect($types->metrics())
                ->map(fn (array $metric, string $key) => ['key' => $key, ...$metric])
                ->values(),
            'thresholds' => [
                'staleAfterSeconds' => config('sensors.stale_after_seconds'),
                'offlineAfterSeconds' => config('sensors.offline_after_seconds'),
            ],
            'latest' => $readings->latestPerRoom(),
            'history' => $readings->history($range),
            'recent' => $readings->recent(),
        ]);
    }
}
