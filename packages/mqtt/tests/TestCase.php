<?php

namespace Joelfrens\HomeAutomation\Mqtt\Tests;

use Joelfrens\HomeAutomation\Mqtt\MqttServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/** A minimal Laravel app with only this package installed. No database is used. */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MqttServiceProvider::class];
    }
}
