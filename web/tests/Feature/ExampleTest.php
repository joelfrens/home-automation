<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_dashboard()
    {
        $this->get(route('home'))->assertRedirect('/dashboard');
    }

    public function test_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
    }
}
