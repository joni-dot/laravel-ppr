<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_safe_user_fields_are_sent_to_the_browser()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'member',
                ])
                ->where('auth.can.manageUsers', false)
                ->where('auth.can.manageSettings', false)
            );
    }

    public function test_admins_are_told_they_can_manage_users_and_settings()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.can.manageUsers', true)
                ->where('auth.can.manageSettings', true)
            );
    }

    public function test_guests_have_no_user()
    {
        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('auth.user', null));
    }
}
