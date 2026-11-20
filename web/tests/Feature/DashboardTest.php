<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Joelfrens\HomeAutomation\Sensors\Database\Factories\MeasurementFactory;
use Tests\TestCase;

/**
 * Page-level tests only. The data logic (latest, history averaging, ranges) is tested
 * in the sensors package itself: packages/sensors/tests.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_empty_dashboard_renders_with_metric_definitions()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('range', '24h')
                ->where('metrics.0', ['key' => 'temperature', 'label' => 'Temperature', 'unit' => '°C'])
                ->has('metrics', 3)
                ->where('thresholds.staleAfterSeconds', 30)
                ->has('latest', 0)
                ->has('history', 0)
                ->has('recent', 0));
    }

    public function test_dashboard_shows_data_from_the_sensors_package()
    {
        MeasurementFactory::message('kitchen', now()->subSeconds(5), ['temperature' => 21.5, 'humidity' => 45, 'pressure' => 1001]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('latest', 1)
                ->where('latest.0.room', 'kitchen')
                ->where('latest.0.values.temperature', 21.5)
                ->has('history', 3) // one point per metric
                ->has('recent', 1));
    }

    public function test_range_comes_from_the_query_string()
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard?range=6h')
            ->assertInertia(fn (Assert $page) => $page->where('range', '6h'));
    }

    public function test_unknown_or_odd_range_falls_back_to_24_hours()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard?range=forever')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('range', '24h'));

        $this->actingAs($user)->get('/dashboard?range[]=6h')->assertOk();
    }
}
