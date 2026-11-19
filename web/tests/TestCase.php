<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: RefreshDatabase drops every table before the tests run.
     * Refuse unless the connection is the throwaway in-memory SQLite database,
     * so a misconfigured environment can never wipe the real home database.
     * (Hooked into setUpTraits, which runs right before RefreshDatabase does its work.
     * Not beforeRefreshingDatabase: the trait's own empty version would override it.)
     */
    protected function setUpTraits()
    {
        $this->refuseToResetARealDatabase();

        return parent::setUpTraits();
    }

    private function refuseToResetARealDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to reset database [{$connection}:{$database}]. Tests must use in-memory SQLite (see phpunit.xml)."
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
