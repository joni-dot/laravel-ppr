<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_sessions_are_signed_out_when_the_password_changes()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['password' => 'a-new-password']);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('inertia.clear_history', true);
        $this->assertGuest();
    }

    public function test_a_save_from_a_signed_out_session_is_sent_to_the_login_page_with_get()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['password' => 'a-new-password']);

        $this->patch(route('profile.update'), ['name' => 'X', 'email' => $user->email], ['X-Inertia' => 'true'])
            ->assertStatus(303)
            ->assertRedirect(route('login'));
    }

    public function test_an_expired_session_lands_on_a_login_page_that_clears_the_browser_history()
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('inertia.clear_history', true);
    }

    public function test_the_session_that_changes_the_password_stays_signed_in()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_signing_out_clears_the_browser_history()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertSessionHas('inertia.clear_history', true);
    }

    public function test_pages_ask_the_browser_to_encrypt_its_history()
    {
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get(route('dashboard'))->viewData('page');

        $this->assertTrue($page['encryptHistory']);
    }

    public function test_only_the_app_url_host_is_trusted()
    {
        config(['app.url' => 'https://ppr.example.com']);

        $this->assertSame(['^ppr\.example\.com$'], app(TrustHosts::class)->hosts());
    }
}
