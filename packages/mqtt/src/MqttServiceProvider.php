<?php

namespace Joelfrens\HomeAutomation\Mqtt;

use Illuminate\Support\ServiceProvider;
use Joelfrens\HomeAutomation\Mqtt\Console\ListenCommand;

/** Connects this package to a Laravel app; found automatically via composer.json "extra.laravel". */
class MqttServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mqtt.php', 'mqtt');

        $this->app->singleton(MqttConnector::class, fn ($app) => new MqttConnector($app['config']->get('mqtt')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ListenCommand::class]);

            $this->publishes([
                __DIR__.'/../config/mqtt.php' => config_path('mqtt.php'),
            ], 'mqtt-config');
        }
    }
}
