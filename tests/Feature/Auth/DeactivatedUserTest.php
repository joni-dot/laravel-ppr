<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_is_signed_out_on_their_next_request()
    {
        $user = User::factory()->deactivated()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deactivated.')
            ->assertSessionHas('inertia.clear_history', true);

        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_stay_logged_in_after_entering_the_right_password()
    {
        $user = User::factory()->deactivated()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
