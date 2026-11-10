<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\LimitPasswordResetAttempts;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\UserInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordLink::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordLink::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordLink::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_the_reset_link_points_to_the_app_whatever_host_the_request_names()
    {
        Notification::fake();
        config(['app.url' => 'https://ppr.example.com']);

        $user = User::factory()->create();

        $this->post('http://attacker.example/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://ppr.example.com/reset-password/');
        });
    }

    public function test_the_reset_email_is_sent_in_the_background()
    {
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordLink('token'));
    }

    public function test_known_and_unknown_addresses_get_the_same_answer()
    {
        Notification::fake();

        $user = User::factory()->create();
        $answer = 'If an account uses this email address, we have emailed it a password reset link.';

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $answer);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $answer);
    }

    public function test_asking_again_too_soon_for_one_account_gets_the_same_answer()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If an account uses this email address, we have emailed it a password reset link.');
    }

    public function test_reset_links_are_limited_per_ip_address()
    {
        Notification::fake();

        foreach (range(1, LimitPasswordResetAttempts::ATTEMPTS_PER_MINUTE) as $attempt) {
            $this->post(route('password.email'), ['email' => "guess{$attempt}@example.com"])
                ->assertSessionHasNoErrors();
        }

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSentTo($user);
    }

    public function test_password_reset_attempts_are_limited_per_ip_address()
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $attempt = fn () => $this->post(route('password.update'), [
            'token' => 'guessed-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        foreach (range(1, LimitPasswordResetAttempts::ATTEMPTS_PER_MINUTE) as $ignored) {
            $attempt();
        }

        $attempt()->assertSessionHasErrors([
            'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => 60]),
        ]);
    }

    public function test_a_made_up_token_gets_the_same_answer_for_known_and_unknown_addresses()
    {
        $user = User::factory()->create();
        $answer = 'This password reset link is invalid or has expired.';

        foreach ([$user->email, 'nobody@example.com'] as $email) {
            $this->post(route('password.update'), [
                'token' => 'made-up-token',
                'email' => $email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasErrors(['email' => $answer]);
        }
    }

    public function test_the_reset_and_invitation_emails_are_encrypted_in_the_queue()
    {
        $this->assertInstanceOf(ShouldBeEncrypted::class, new ResetPasswordLink('token'));
        $this->assertInstanceOf(ShouldBeEncrypted::class, new UserInvitation('token'));
    }

    public function test_the_default_reset_wait_is_well_above_the_time_to_hash_a_token()
    {
        preg_match("/env\('AUTH_TIMEBOX_DURATION', ([\d_]+)\)/", file_get_contents(config_path('auth.php')), $match);
        $defaultWait = (int) str_replace('_', '', $match[1]);

        $start = hrtime(true);
        Hash::driver('bcrypt')->make('reset-token', ['rounds' => 12]);
        $hashing = (hrtime(true) - $start) / 1000;

        $this->assertGreaterThan(2 * $hashing, $defaultWait, 'Real addresses would answer slower than unknown ones.');
    }
}
