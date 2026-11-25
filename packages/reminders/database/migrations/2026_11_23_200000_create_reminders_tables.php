<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * children – who an event is for (colour-coded on the page)
 * events   – one row per school trip / PE day / payment due ... (times in UTC)
 * reminders – when to push a notification for an event; sent_at stops double sends
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('colour', 20)->default('sky');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->string('title', 200);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location', 200)->nullable();
            $table->string('needs', 500)->nullable();   // "PE kit", "£2 for the cake sale"
            $table->text('notes')->nullable();
            $table->string('source_path')->nullable();  // the letter/screenshot it came from
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');  // 0 = same day
            $table->string('time', 5);                    // "19:00", household time
            $table->timestampTz('remind_at');             // the moment above, in UTC
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('skipped_at')->nullable(); // event already started (e.g. after downtime)
            $table->timestamps();

            $table->index(['sent_at', 'skipped_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('events');
        Schema::dropIfExists('children');
    }
};
