<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime(); // "now" stays fixed, so time ranges are predictable
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_empty_dashboard_renders_without_readings()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('range', '24h')
                ->has('latest', 0)
                ->has('history', 0)
                ->has('recent', 0));
    }

    public function test_cards_show_the_newest_reading_of_each_room()
    {
        SensorReading::factory()->create(['room' => 'kitchen', 'temperature_c' => 18, 'recorded_at' => now()->subMinutes(10)]);
        SensorReading::factory()->create(['room' => 'kitchen', 'temperature_c' => 21.5, 'recorded_at' => now()->subSeconds(5)]);
        SensorReading::factory()->create(['room' => 'bedroom', 'temperature_c' => 19, 'recorded_at' => now()->subSeconds(3)]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('latest', 2)
                ->where('latest.0.room', 'bedroom') // sorted by room name
                ->where('latest.1.room', 'kitchen')
                ->where('latest.1.temperature', 21.5)
                ->has('recent', 3));
    }

    public function test_history_averages_readings_into_time_buckets()
    {
        // 24h range uses 5-minute buckets. Two readings in the same bucket are averaged.
        $bucketStart = now()->subHour()->startOfHour();
        SensorReading::factory()->create(['temperature_c' => 20, 'humidity_pct' => 40, 'recorded_at' => $bucketStart->copy()->addSeconds(10)]);
        SensorReading::factory()->create(['temperature_c' => 22, 'humidity_pct' => 50, 'recorded_at' => $bucketStart->copy()->addSeconds(200)]);
        // A later bucket
        SensorReading::factory()->create(['temperature_c' => 25, 'recorded_at' => $bucketStart->copy()->addMinutes(10)]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 2)
                ->where('history.0.t', $bucketStart->getTimestamp() * 1000)
                ->where('history.0.temperature', 21)
                ->where('history.0.humidity', 45)
                ->where('history.1.temperature', 25));
    }

    public function test_history_only_includes_the_selected_range()
    {
        SensorReading::factory()->create(['recorded_at' => now()->subHours(2)]);
        SensorReading::factory()->create(['recorded_at' => now()->subHours(12)]);
        SensorReading::factory()->create(['recorded_at' => now()->subDays(3)]);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard', ['range' => '6h']))
            ->assertInertia(fn (Assert $page) => $page->where('range', '6h')->has('history', 1));

        $this->actingAs($user)->get(route('dashboard', ['range' => '24h']))
            ->assertInertia(fn (Assert $page) => $page->has('history', 2));

        $this->actingAs($user)->get(route('dashboard', ['range' => '7d']))
            ->assertInertia(fn (Assert $page) => $page->has('history', 3));
    }

    public function test_unknown_range_falls_back_to_24_hours()
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard?range=forever')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('range', '24h'));

        $this->get('/dashboard?range[]=6h')->assertOk(); // odd input must not crash
    }
}
