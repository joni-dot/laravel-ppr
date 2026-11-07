<?php

namespace Tests\Feature\Auth;

use App\Actions\Users\SendInvitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AcceptInvitationTest extends TestCase
{
    use RefreshDatabase;

    private const string NEW_PASSWORD = 'correct-horse-battery';

    public function test_the_invitation_email_links_to_the_set_password_page()
    {
        Notification::fake();
        $user = User::factory()->invited()->create();

        app(SendInvitation::class)->handle($user);

        Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $invitation) use ($user) {
            $link = $invitation->toMail($user)->actionUrl;

            return str_starts_with($link, url('invitation').'/')
                && str_contains($link, 'email='.urlencode($user->email));
        });
    }

    public function test_a_valid_invitation_link_shows_the_form()
    {
        [$user, $token] = $this->invite();

        $this->get(route('invitation.show', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/AcceptInvitation')
                ->where('isValid', true)
                ->where('email', $user->email)
            );
    }

    public function test_an_invalid_invitation_link_says_so()
    {
        [$user] = $this->invite();

        $this->get(route('invitation.show', ['token' => 'wrong-token', 'email' => $user->email]))
            ->assertInertia(fn ($page) => $page->where('isValid', false));

        $this->get(route('invitation.show', ['token' => 'wrong-token', 'email' => 'nobody@example.com']))
            ->assertInertia(fn ($page) => $page->where('isValid', false));
    }

    public function test_user_can_set_a_password_and_then_log_in()
    {
        [$user, $token] = $this->invite();

        $this->post(route('invitation.store'), $this->acceptPayload($user, $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
        $this->assertDatabaseMissing('invitation_tokens', ['email' => $user->email]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invitation_link_works_only_once()
    {
        [$user, $token] = $this->invite();

        $this->post(route('invitation.store'), $this->acceptPayload($user, $token));
        $this->post(route('invitation.store'), $this->acceptPayload($user, $token, 'another-password-123'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
    }

    public function test_an_invitation_link_expires_after_three_days()
    {
        [$user, $token] = $this->invite();

        $this->travel(3)->days();
        $this->travel(1)->minute();

        $this->post(route('invitation.store'), $this->acceptPayload($user, $token))
            ->assertSessionHasErrors('email');

        $this->assertNull($user->refresh()->password);
    }

    public function test_a_password_reset_token_cannot_be_used_as_an_invitation()
    {
        $user = User::factory()->invited()->create();
        $resetToken = app('auth.password.broker')->createToken($user);

        $this->post(route('invitation.store'), $this->acceptPayload($user, $resetToken))
            ->assertSessionHasErrors('email');
    }

    public function test_passwords_must_match()
    {
        [$user, $token] = $this->invite();

        $this->post(route('invitation.store'), [
            ...$this->acceptPayload($user, $token),
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
    }

    public function test_invited_users_cannot_log_in_before_setting_a_password()
    {
        $user = User::factory()->invited()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => '']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'anything']);

        $this->assertGuest();
    }

    /**
     * @return array{User, string}
     */
    private function invite(): array
    {
        $user = User::factory()->invited()->create();

        return [$user, Password::broker('invitations')->createToken($user)];
    }

    /**
     * @return array<string, string>
     */
    private function acceptPayload(User $user, string $token, string $password = self::NEW_PASSWORD): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
