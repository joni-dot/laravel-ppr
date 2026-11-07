<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_can_visit_the_dashboard()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}
