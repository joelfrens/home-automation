<?php

namespace Joelfrens\HomeAutomation\Weather;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The outdoor forecast from Open-Meteo (https://open-meteo.com), for the configured location.
 *
 * One request fetches today + tomorrow hour by hour, and is cached (30 minutes by default),
 * so every screen and widget shares it. If the service is down or answers nonsense, callers
 * get null and the app carries on: weather is a nice-to-have, never a reason for an error page.
 *
 * Times are asked for as Unix timestamps (UTC), so clock changes can't confuse the hours.
 */
class Forecast
{
    /**
     * @param  array{latitude: mixed, longitude: mixed, timezone: string, base_url: string, timeout: int, cache_minutes: int, retry_after_minutes: int, forecast_days: int}  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    /** False when no location is configured: nothing is fetched. */
    public function enabled(): bool
    {
        return $this->location() !== null;
    }

    public function current(): ?Current
    {
        $data = $this->data();

        if ($data === null) {
            return null;
        }

        return new Current(
            temperature: $data['current']['temperature'],
            time: CarbonImmutable::createFromTimestamp($data['current']['time'], 'UTC'),
        );
    }

    /**
     * The weather during a window, e.g. this morning's school run (07:45 to 08:45).
     * Uses every forecast hour from the hour the window starts in up to its end.
     * Null when the weather is off or unavailable, or the window is outside the forecast.
     */
    public function between(CarbonInterface $from, CarbonInterface $to): ?Outlook
    {
        $data = $this->data();

        if ($data === null) {
            return null;
        }

        $start = $from->copy()->startOfHour()->getTimestamp();
        $end = $to->getTimestamp();

        $hours = array_values(array_filter(
            $data['hours'],
            fn (array $hour) => $hour['time'] >= $start && $hour['time'] <= $end,
        ));

        if ($hours === []) {
            return null;
        }

        $temperatures = array_column($hours, 'temperature');
        $rain = array_filter(array_column($hours, 'rain'), fn ($chance) => $chance !== null);

        return new Outlook(
            minTemperature: min($temperatures),
            maxTemperature: max($temperatures),
            rainChance: $rain === [] ? 0 : max($rain),
            hours: count($hours),
        );
    }

    /**
     * The cleaned-up forecast, from the cache or a fresh request.
     *
     * @return array{current: array{temperature: float, time: int}, hours: list<array{time: int, temperature: float, rain: int|null}>}|null
     */
    private function data(): ?array
    {
        $location = $this->location();

        if ($location === null) {
            return null;
        }

        $key = "weather:forecast:{$location['latitude']},{$location['longitude']}";

        if ($this->cache->has("{$key}:failed")) {
            return null;
        }

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            /** @var array{current: array{temperature: float, time: int}, hours: list<array{time: int, temperature: float, rain: int|null}>} $cached */
            return $cached;
        }

        $data = $this->fetch($location);

        if ($data === null) {
            $this->cache->put("{$key}:failed", true, now()->addMinutes($this->config['retry_after_minutes']));

            return null;
        }

        $this->cache->put($key, $data, now()->addMinutes($this->config['cache_minutes']));

        return $data;
    }

    /**
     * @param  array{latitude: float, longitude: float}  $location
     * @return array{current: array{temperature: float, time: int}, hours: list<array{time: int, temperature: float, rain: int|null}>}|null
     */
    private function fetch(array $location): ?array
    {
        try {
            $response = $this->http
                ->timeout($this->config['timeout'])
                ->acceptJson()
                ->get($this->config['base_url'], [
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'current' => 'temperature_2m',
                    'hourly' => 'temperature_2m,precipitation_probability',
                    'timeformat' => 'unixtime',
                    'forecast_days' => $this->config['forecast_days'],
                ]);
        } catch (Throwable $e) {
            Log::warning('Weather: request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Weather: unexpected response', ['status' => $response->status()]);

            return null;
        }

        $data = $this->parse($response->json());

        if ($data === null) {
            Log::warning('Weather: response not in the expected format');
        }

        return $data;
    }

    /**
     * Check the answer's shape and keep only what we use.
     *
     * @return array{current: array{temperature: float, time: int}, hours: list<array{time: int, temperature: float, rain: int|null}>}|null
     */
    private function parse(mixed $json): ?array
    {
        $current = is_array($json) ? ($json['current'] ?? null) : null;
        $hourly = is_array($json) ? ($json['hourly'] ?? null) : null;

        if (! is_array($current) || ! is_numeric($current['temperature_2m'] ?? null) || ! is_int($current['time'] ?? null)
            || ! is_array($hourly) || ! is_array($hourly['time'] ?? null)
            || ! is_array($hourly['temperature_2m'] ?? null) || ! is_array($hourly['precipitation_probability'] ?? null)
            || count($hourly['time']) !== count($hourly['temperature_2m'])
            || count($hourly['time']) !== count($hourly['precipitation_probability'])) {
            return null;
        }

        $hours = [];

        foreach ($hourly['time'] as $i => $time) {
            $temperature = $hourly['temperature_2m'][$i];
            $rain = $hourly['precipitation_probability'][$i];

            // Open-Meteo leaves gaps as null; skip hours without a temperature.
            if (! is_int($time) || ! is_numeric($temperature)) {
                continue;
            }

            $hours[] = [
                'time' => $time,
                'temperature' => (float) $temperature,
                'rain' => is_numeric($rain) ? (int) round((float) $rain) : null,
            ];
        }

        if ($hours === []) {
            return null;
        }

        return [
            'current' => ['temperature' => (float) $current['temperature_2m'], 'time' => $current['time']],
            'hours' => $hours,
        ];
    }

    /** @return array{latitude: float, longitude: float}|null rounded to ~1 km */
    private function location(): ?array
    {
        $latitude = $this->config['latitude'];
        $longitude = $this->config['longitude'];

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return [
            'latitude' => round((float) $latitude, 2),
            'longitude' => round((float) $longitude, 2),
        ];
    }
}
