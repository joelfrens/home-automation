<?php

namespace Joelfrens\HomeAutomation\Weather\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Joelfrens\HomeAutomation\Weather\Forecast;

/**
 * Quick check that the weather works: php artisan weather:show
 */
class ShowWeather extends Command
{
    protected $signature = 'weather:show {--hours=3 : How many hours ahead to summarise}';

    protected $description = 'Show the outdoor temperature now and the next few hours (Open-Meteo)';

    public function handle(Forecast $forecast): int
    {
        if (! $forecast->enabled()) {
            $this->warn('Weather is off: set WEATHER_LATITUDE and WEATHER_LONGITUDE in .env');

            return self::FAILURE;
        }

        $timezone = (string) config('weather.timezone');
        $current = $forecast->current();

        if ($current === null) {
            $this->error('No forecast: the weather service could not be reached (see the log). Retrying in a few minutes.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $now = CarbonImmutable::now($timezone);
        $outlook = $forecast->between($now, $now->addHours($hours));

        $this->line(sprintf(
            'Now: %.1f °C (as of %s)',
            $current->temperature,
            $current->time->setTimezone($timezone)->format('H:i'),
        ));

        if ($outlook !== null) {
            $this->line(sprintf(
                'Next %d h: %.1f to %.1f °C, rain chance up to %d%%',
                $hours,
                $outlook->minTemperature,
                $outlook->maxTemperature,
                $outlook->rainChance,
            ));
        }

        return self::SUCCESS;
    }
}
