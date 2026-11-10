<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\EmailAddressChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_name_can_be_changed_without_the_password()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => strtoupper($user->email)])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->refresh()->name);
    }

    public function test_changing_the_email_needs_the_current_password()
    {
        $user = User::factory()->create();
        $email = $user->email;

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'outsider@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'outsider@example.com',
                'current_password' => 'not-the-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($email, $user->refresh()->email);
    }

    public function test_the_old_address_is_told_when_the_email_changes()
    {
        Notification::fake();

        $user = User::factory()->create();
        $oldEmail = $user->email;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'current_password' => 'password',
        ]);

        Notification::assertSentOnDemand(
            EmailAddressChanged::class,
            fn (EmailAddressChanged $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $oldEmail
                && $notification->newEmail === 'new@example.com',
        );
    }

    public function test_nobody_is_told_when_the_email_stays_the_same()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Other Name', 'email' => $user->email]);

        Notification::assertNothingSent();
    }

    public function test_profile_saves_have_their_own_limit()
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $ignored) {
            $this->actingAs($user)->put(route('user-password.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
        }

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Still Allowed', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));
    }
}
