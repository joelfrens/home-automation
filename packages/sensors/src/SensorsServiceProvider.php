<?php

namespace Joelfrens\HomeAutomation\Sensors;

use Illuminate\Support\ServiceProvider;

/**
 * The "plug" that connects this package to a Laravel app. Laravel finds it automatically
 * (see "extra.laravel.providers" in composer.json) when the package is installed.
 */
class SensorsServiceProvider extends ServiceProvider
{
    /** Register services: what the package offers to the app's service container. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sensors.php', 'sensors');

        // One shared instance each; anything that type-hints these classes receives them.
        $this->app->singleton(SensorTypes::class, fn ($app) => SensorTypes::fromFile(
            $app['config']->get('sensors.types_path'),
        ));

        $this->app->singleton(SensorMessage::class, fn ($app) => new SensorMessage(
            $app->make(SensorTypes::class),
        ));

        $this->app->singleton(SensorReadings::class, fn ($app) => new SensorReadings(
            $app->make(SensorTypes::class),
            (int) $app['config']->get('sensors.latest_lookback_hours'),
        ));
    }

    /** Boot: hook into the running app (migrations, publishable config). */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sensors.php' => config_path('sensors.php'),
            ], 'sensors-config');
        }
    }
}
