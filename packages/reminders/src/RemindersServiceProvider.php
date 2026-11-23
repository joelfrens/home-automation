<?php

namespace Joelfrens\HomeAutomation\Reminders;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Joelfrens\HomeAutomation\Reminders\Console\SendDueReminders;

/**
 * Plugs the reminders package into the Laravel app (auto-discovered via composer.json).
 */
class RemindersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reminders.php', 'reminders');

        $this->app->singleton(EventPlanner::class, fn ($app) => new EventPlanner($app['config']->get('reminders.timezone')));
        $this->app->singleton(EventExtractor::class, fn ($app) => new EventExtractor($app['config']->get('reminders.timezone')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SendDueReminders::class]);

            $this->publishes([
                __DIR__.'/../config/reminders.php' => config_path('reminders.php'),
            ], 'reminders-config');
        }

        // Check for due reminders every minute (needs `php artisan schedule:work` running).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('reminders:send')->everyMinute()->withoutOverlapping();
        });
    }
}
