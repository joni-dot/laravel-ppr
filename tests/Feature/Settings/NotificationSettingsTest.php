<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_persons_own_period()
    {
        $user = User::factory()->create();
        $user->forceFill(['notification_days' => 14])->save();

        $this->actingAs($user)
            ->get(route('notification-settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Notifications')
                ->where('notificationDays', 14)
                ->where('maxNotificationDays', 90)
            );
    }

    public function test_new_people_are_reminded_a_week_ahead()
    {
        $this->assertSame(7, User::factory()->create()->fresh()->notification_days);
    }

    public function test_a_person_changes_only_their_own_period()
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();

        $this->actingAs($user)
            ->put(route('notification-settings.update'), ['notification_days' => 30])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('notification-settings.edit'));

        $this->assertSame(30, $user->fresh()->notification_days);
        $this->assertSame(7, $someoneElse->fresh()->notification_days);
    }

    public function test_the_period_can_be_anything_from_0_to_90_days()
    {
        $user = User::factory()->create();

        foreach ([0, 90] as $days) {
            $this->actingAs($user)
                ->put(route('notification-settings.update'), ['notification_days' => $days])
                ->assertSessionHasNoErrors();

            $this->assertSame($days, $user->fresh()->notification_days);
        }

        foreach ([-1, 91, 2.5, 'week', null] as $invalid) {
            $this->actingAs($user)
                ->put(route('notification-settings.update'), ['notification_days' => $invalid])
                ->assertSessionHasErrors('notification_days');
        }

        $this->assertSame(90, $user->fresh()->notification_days);
    }

    public function test_the_database_refuses_a_period_out_of_range()
    {
        $this->expectException(QueryException::class);

        User::factory()->create()->forceFill(['notification_days' => 91])->save();
    }

    public function test_guests_are_sent_to_sign_in()
    {
        $this->get(route('notification-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('notification-settings.update'), ['notification_days' => 3])->assertRedirect(route('login'));
    }
}
