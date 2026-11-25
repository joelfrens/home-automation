<?php

namespace Joelfrens\HomeAutomation\Weather;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Joelfrens\HomeAutomation\Weather\Console\ShowWeather;

/**
 * Plugs the weather package into the Laravel app (auto-discovered via composer.json).
 */
class WeatherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/weather.php', 'weather');

        $this->app->singleton(Forecast::class, fn ($app) => new Forecast(
            $app['config']->get('weather'),
            $app->make(Http::class),
            $app['cache']->store(),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ShowWeather::class]);

            $this->publishes([
                __DIR__.'/../config/weather.php' => config_path('weather.php'),
            ], 'weather-config');
        }
    }
}
