<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sensor_readings (one column per BME280 value) is replaced by the generic `measurements`
 * table from the joelfrens/home-automation-sensors package. It was empty when we switched,
 * so it is dropped — but only if it is still empty. If it has rows, stop instead of losing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sensor_readings')) {
            return;
        }

        $rows = DB::table('sensor_readings')->count();

        if ($rows > 0) {
            throw new RuntimeException(
                "sensor_readings still has {$rows} row(s). Copy them into measurements before dropping it."
            );
        }

        Schema::drop('sensor_readings');
    }

    public function down(): void
    {
        // Nothing to restore: the old table held no data.
    }
};
