<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sensor_readings table is written by the Node ingestion service and was first
 * created by db/init.sql. This migration mirrors that definition so Laravel (and the
 * tests) know about it. On the real database the table already exists, so it is a no-op.
 * The final storage design (generic measurements table or not) is still to be decided.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sensor_readings')) {
            return;
        }

        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->text('room');
            $table->text('sensor');
            $table->float('temperature_c');
            $table->float('humidity_pct');
            $table->float('pressure_hpa');
            $table->timestampTz('recorded_at')->useCurrent();
            $table->index(['room', 'recorded_at']);
        });
    }

    public function down(): void
    {
        // Deliberately empty: never drop real sensor data on a rollback.
    }
};
