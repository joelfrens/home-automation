<?php

namespace Joelfrens\HomeAutomation\Sensors\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Joelfrens\HomeAutomation\Sensors\SensorsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

/**
 * Orchestra Testbench boots a minimal Laravel app with only this package installed,
 * so the package is tested on its own, without the host app.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [SensorsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Safety net: RefreshDatabase drops every table. Refuse unless the database is the
     * throwaway in-memory SQLite one (runs right before RefreshDatabase does its work).
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
