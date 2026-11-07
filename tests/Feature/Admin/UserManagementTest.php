<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminOnlyRoutes(): array
    {
        return [
            'list users' => ['get', 'admin.users.index'],
            'invite user' => ['post', 'admin.users.store'],
            'update user' => ['patch', 'admin.users.update'],
            'deactivate user' => ['post', 'admin.users.deactivate'],
            'reactivate user' => ['post', 'admin.users.reactivate'],
            'resend invitation' => ['post', 'admin.users.invitation.resend'],
        ];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_guests_are_sent_to_login(string $method, string $route)
    {
        $target = User::factory()->create();

        $this->call($method, route($route, $target))->assertRedirect(route('login'));
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_members_are_forbidden(string $method, string $route)
    {
        $target = User::factory()->invited()->deactivated()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, route($route, $target), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'])
            ->assertForbidden();
    }

    public function test_admin_sees_users_without_secrets()
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->invited()->create(['name' => 'Ivan Invited']);
        User::factory()->deactivated()->create(['name' => 'Dan Deactivated']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('$2y$', escape: false)
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 3)
                ->where('users.data.0.name', 'Ada Admin')
                ->where('users.data.0.status', 'active')
                ->where('users.data.0.can.update', false)
                ->where('users.data.0.can.deactivate', false)
                ->where('users.data.1.status', 'deactivated')
                ->where('users.data.1.can.reactivate', true)
                ->where('users.data.2.status', 'invited')
                ->where('users.data.2.can.resendInvitation', true)
                ->missing('users.data.0.password')
            );
    }

    public function test_admin_can_search_users_by_name_or_email()
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
        User::factory()->create(['name' => 'Bob Member', 'email' => 'bob@supplies.test']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'SUPPLIES']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Bob Member')
                ->where('filters.search', 'SUPPLIES')
            );
    }

    public function test_admin_can_invite_a_user()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nina New',
                'email' => 'Nina@Example.com',
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::firstWhere('email', 'nina@example.com');

        $this->assertSame(Role::Admin, $user->role);
        $this->assertNull($user->password);
        $this->assertDatabaseHas('invitation_tokens', ['email' => 'nina@example.com']);
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_invite_ignores_fields_an_admin_should_not_set()
    {
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Nina New',
                'email' => 'nina@example.com',
                'role' => 'member',
                'password' => 'chosen-by-admin',
                'deactivated_at' => now()->toDateTimeString(),
            ]);

        $user = User::firstWhere('email', 'nina@example.com');

        $this->assertNull($user->password);
        $this->assertNull($user->deactivated_at);
    }

    public function test_invite_is_validated()
    {
        $admin = User::factory()->admin()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'TAKEN@example.com',
                'role' => 'superuser',
            ])
            ->assertSessionHasErrors(['name', 'email', 'role']);
    }

    public function test_admin_can_change_name_and_role()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), ['name' => 'Renamed', 'role' => 'admin'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame(Role::Admin, $user->role);
    }

    public function test_admin_cannot_change_their_own_role()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), ['name' => 'Me', 'role' => 'member'])
            ->assertForbidden();

        $this->assertSame(Role::Admin, $admin->refresh()->role);
    }

    public function test_admin_can_deactivate_a_user_and_their_invitation_stops_working()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        DB::table('invitation_tokens')->insert(['email' => $user->email, 'token' => 'hashed', 'created_at' => now()]);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $user));

        $this->assertTrue($user->refresh()->isDeactivated());
        $this->assertDatabaseMissing('invitation_tokens', ['email' => $user->email]);
    }

    public function test_admin_cannot_deactivate_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $admin))
            ->assertForbidden();

        $this->assertFalse($admin->refresh()->isDeactivated());
    }

    public function test_admin_can_reactivate_a_user()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->deactivated()->create();

        $this->actingAs($admin)->post(route('admin.users.reactivate', $user));

        $this->assertFalse($user->refresh()->isDeactivated());
    }

    public function test_admin_can_resend_an_invitation()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();

        $this->actingAs($admin)->post(route('admin.users.invitation.resend', $user));

        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_invitations_cannot_be_resent_to_active_users()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.invitation.resend', User::factory()->create()))
            ->assertForbidden();
    }
}
