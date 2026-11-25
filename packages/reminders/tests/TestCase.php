<?php

namespace Joelfrens\HomeAutomation\Reminders\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Joelfrens\HomeAutomation\Reminders\RemindersServiceProvider;
use Joelfrens\HomeAutomation\Reminders\Tests\Fixtures\User;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

/**
 * A minimal Laravel app with only this package (and laravel/ai) installed.
 * Never talks to a real AI: tests fake the ExtractEvents agent.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AiServiceProvider::class, RemindersServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('reminders.timezone', 'Europe/London');
        $app['config']->set('reminders.ntfy.server', 'https://ntfy.example');
        $app['config']->set('reminders.storage.disk', 'local');
    }

    protected function defineDatabaseMigrations(): void
    {
        // The host app owns "users"; the package only points at it.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->string('ntfy_topic')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Safety net: RefreshDatabase drops every table. Refuse unless the database is the
     * throwaway in-memory SQLite one.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to reset database [{$connection}:{$driver}:{$database}]. Tests must use in-memory SQLite."
            );
        }

        return parent::setUpTraits();
    }
}
