<?php

namespace Joelfrens\HomeAutomation\Weather\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Joelfrens\HomeAutomation\Weather\Forecast;

class ForecastTest extends TestCase
{
    private function forecast(): Forecast
    {
        return $this->app->make(Forecast::class);
    }

    private function london(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Europe/London');
    }

    /** A winter morning (UTC = London time in November). */
    private function fakeMorning(): void
    {
        Http::fake(['weather.test/*' => Http::response($this->openMeteo(2.4, [
            '2026-11-26 06:00' => [1.2, 0],
            '2026-11-26 07:00' => [0.8, 10],
            '2026-11-26 08:00' => [1.5, 40],
            '2026-11-26 09:00' => [3.9, 70],
            '2026-11-26 10:00' => [5.1, 20],
        ]))]);
    }

    public function test_current_temperature(): void
    {
        $this->fakeMorning();

        $current = $this->forecast()->current();

        $this->assertNotNull($current);
        $this->assertSame(2.4, $current->temperature);
        $this->assertSame('2026-11-26 07:45', $current->time->format('Y-m-d H:i'));
    }

    public function test_outlook_for_a_window_uses_the_hours_it_touches(): void
    {
        $this->fakeMorning();

        // 07:45–08:45 touches the 07:00 and 08:00 hours
        $outlook = $this->forecast()->between($this->london('2026-11-26 07:45'), $this->london('2026-11-26 08:45'));

        $this->assertNotNull($outlook);
        $this->assertSame(0.8, $outlook->minTemperature);
        $this->assertSame(1.5, $outlook->maxTemperature);
        $this->assertSame(40, $outlook->rainChance);
        $this->assertSame(2, $outlook->hours);
    }

    public function test_window_outside_the_forecast_gives_null(): void
    {
        $this->fakeMorning();

        $this->assertNull($this->forecast()->between($this->london('2026-11-30 08:00'), $this->london('2026-11-30 09:00')));
    }

    public function test_hours_without_a_rain_chance_or_temperature_are_handled(): void
    {
        Http::fake(['weather.test/*' => Http::response($this->openMeteo(4.0, [
            '2026-11-26 07:00' => [4.0, null],
            '2026-11-26 08:00' => [null, 30],
            '2026-11-26 09:00' => [6.0, null],
        ]))]);

        $outlook = $this->forecast()->between($this->london('2026-11-26 07:00'), $this->london('2026-11-26 09:00'));

        $this->assertNotNull($outlook);
        $this->assertSame(4.0, $outlook->minTemperature);
        $this->assertSame(6.0, $outlook->maxTemperature);
        $this->assertSame(0, $outlook->rainChance);
        $this->assertSame(2, $outlook->hours);
    }

    public function test_summer_time_windows_map_to_the_right_utc_hours(): void
    {
        // 30 June: London is UTC+1, so 08:00 local is 07:00 UTC
        Http::fake(['weather.test/*' => Http::response($this->openMeteo(15.0, [
            '2027-06-30 06:00' => [11.0, 0],
            '2027-06-30 07:00' => [14.0, 5],
            '2027-06-30 08:00' => [17.0, 90],
        ], nowAt: '2027-06-30 06:30'))]);

        $outlook = $this->forecast()->between($this->london('2027-06-30 08:00'), $this->london('2027-06-30 08:30'));

        $this->assertNotNull($outlook);
        $this->assertSame(14.0, $outlook->minTemperature);
        $this->assertSame(14.0, $outlook->maxTemperature);
        $this->assertSame(1, $outlook->hours);
    }

    public function test_one_request_is_shared_through_the_cache(): void
    {
        $this->fakeMorning();

        $this->forecast()->current();
        $this->forecast()->between($this->london('2026-11-26 07:00'), $this->london('2026-11-26 09:00'));
        $this->forecast()->current();

        Http::assertSentCount(1);
    }

    public function test_request_sends_rounded_coordinates_and_asks_for_unix_times(): void
    {
        $this->fakeMorning();

        $this->forecast()->current();

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['latitude'] === '51.51'
                && $query['longitude'] === '-0.13'
                && $query['timeformat'] === 'unixtime'
                && $query['hourly'] === 'temperature_2m,precipitation_probability'
                && $query['current'] === 'temperature_2m';
        });
    }

    public function test_no_location_means_no_request_and_no_weather(): void
    {
        config(['weather.latitude' => null]);
        $this->app->forgetInstance(Forecast::class);
        Http::fake();

        $this->assertFalse($this->forecast()->enabled());
        $this->assertNull($this->forecast()->current());
        Http::assertNothingSent();
    }

    public function test_server_error_gives_null_and_waits_before_trying_again(): void
    {
        Log::spy();
        Http::fake(['weather.test/*' => Http::response('oops', 500)]);

        $this->assertNull($this->forecast()->current());
        $this->assertNull($this->forecast()->current());

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_unreachable_service_gives_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertNull($this->forecast()->current());
        $this->assertNull($this->forecast()->between($this->london('2026-11-26 07:00'), $this->london('2026-11-26 08:00')));
    }

    public function test_unexpected_answer_gives_null(): void
    {
        Http::fake(['weather.test/*' => Http::response(['hourly' => ['time' => [1, 2], 'temperature_2m' => [1.0]]])]);

        $this->assertNull($this->forecast()->current());
    }

    public function test_retries_after_the_wait(): void
    {
        Http::fakeSequence('weather.test/*')
            ->push('down', 503)
            ->push($this->openMeteo(3.0, ['2026-11-26 08:00' => [3.0, 0]]));

        $this->assertNull($this->forecast()->current());

        $this->travel(6)->minutes();

        $this->assertSame(3.0, $this->forecast()->current()?->temperature);
    }

    public function test_show_command(): void
    {
        CarbonImmutable::setTestNow($this->london('2026-11-26 07:50'));
        $this->fakeMorning();

        $this->artisan('weather:show')
            ->expectsOutputToContain('Now: 2.4 °C')
            ->expectsOutputToContain('Next 3 h: 0.8 to 5.1 °C, rain chance up to 70%')
            ->assertSuccessful();
    }
}
