<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic storage for any sensor type: one row per metric per message.
 * New sensor types (water level, air quality, …) need no schema change,
 * only an entry in sensor-types.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('recorded_at', 3)->useCurrent(); // millisecond precision, like JavaScript dates
            $table->text('room');
            $table->text('sensor');   // sensor type from the MQTT topic, e.g. "bme280"
            $table->text('metric');   // e.g. "temperature"
            $table->double('value');
            $table->text('unit');     // e.g. "°C" (copied from sensor-types.json at write time)

            $table->index(['room', 'sensor', 'metric', 'recorded_at']); // one room's history
            $table->index('recorded_at');                                // "everything since …" (charts)
        });
    }

    public function down(): void
    {
        // Deliberately empty: a rollback must never delete real sensor data.
    }
};
