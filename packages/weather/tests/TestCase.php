<?php

namespace Joelfrens\HomeAutomation\Weather\Tests;

use Illuminate\Support\Facades\Http;
use Joelfrens\HomeAutomation\Weather\WeatherServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

/**
 * A minimal Laravel app with only this package installed. No test reaches the real
 * Open-Meteo: Http::preventStrayRequests() fails any request that wasn't faked.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WeatherServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('weather.latitude', '51.507351');
        $app['config']->set('weather.longitude', '-0.127758');
        $app['config']->set('weather.base_url', 'https://weather.test/v1/forecast');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Same safety net as the other packages: never run against anything but in-memory SQLite.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run against database [{$connection}:{$driver}:{$database}]. Tests must use in-memory SQLite."
            );
        }

        return parent::setUpTraits();
    }

    /**
     * An Open-Meteo answer (timeformat=unixtime) with one entry per hour.
     *
     * @param  array<string, array{0: float|null, 1: int|null}>  $hours  "2026-11-26 08:00" (UTC) => [°C, rain %]
     * @return array<string, mixed>
     */
    protected function openMeteo(float $now, array $hours, string $nowAt = '2026-11-26 07:45'): array
    {
        $ts = fn (string $utc) => (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();

        return [
            'latitude' => 51.5,
            'longitude' => -0.13,
            'utc_offset_seconds' => 0,
            'timezone' => 'GMT',
            'current_units' => ['time' => 'unixtime', 'interval' => 'seconds', 'temperature_2m' => '°C'],
            'current' => ['time' => $ts($nowAt), 'interval' => 900, 'temperature_2m' => $now],
            'hourly_units' => ['time' => 'unixtime', 'temperature_2m' => '°C', 'precipitation_probability' => '%'],
            'hourly' => [
                'time' => array_map($ts, array_keys($hours)),
                'temperature_2m' => array_column(array_values($hours), 0),
                'precipitation_probability' => array_column(array_values($hours), 1),
            ],
        ];
    }
}
