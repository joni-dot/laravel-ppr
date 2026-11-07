<?php

namespace Tests\Feature\Notifications;

use App\Enums\PackageAssignment;
use App\Enums\ProjectRole;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\PackageAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPageTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();
        $this->project = Project::factory()->withMember($this->buyer, ProjectRole::Buyer)->create(['name' => 'Harbour crane']);
        $this->package = Package::factory()->for($this->project)->create(['number' => 'HC-0001', 'title' => 'Hoist motor']);
    }

    public function test_the_page_lists_the_persons_own_notifications_newest_first()
    {
        $this->travelTo(now()->subHour());
        $older = $this->notify($this->buyer, PackageAssignment::Owner);
        $this->travelBack();
        $newer = $this->notify($this->buyer, PackageAssignment::Buyer);
        $newer->markAsRead();

        $someoneElse = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($someoneElse)->create(['role' => ProjectRole::Buyer]);
        $this->notify($someoneElse, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifications/Index')
                ->where('notifications.total', 2)
                ->where('notifications.data.0.id', $newer->id)
                ->where('notifications.data.0.isRead', true)
                ->where('notifications.data.1', [
                    'id' => $older->id,
                    'kind' => 'package_assigned',
                    'projectName' => 'Harbour crane',
                    'packageNumber' => 'HC-0001',
                    'packageTitle' => 'Hoist motor',
                    'params' => ['role' => 'owner'],
                    'isRead' => false,
                    'createdAt' => $older->created_at?->toIso8601String(),
                ])
            );
    }

    public function test_notifications_of_projects_the_person_has_left_are_hidden()
    {
        $this->notify($this->buyer, PackageAssignment::Buyer);
        $otherProject = Project::factory()->withMember($this->buyer, ProjectRole::Buyer)->create();
        $this->notify($this->buyer, PackageAssignment::Buyer, Package::factory()->for($otherProject)->create());

        ProjectMember::query()->where('project_id', $otherProject->id)->delete();

        $this->actingAs($this->buyer)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.total', 1)
                ->where('notifications.data.0.packageNumber', 'HC-0001')
                ->where('unreadNotificationCount', 1)
            );
    }

    public function test_admins_see_their_notifications_of_every_project()
    {
        $admin = User::factory()->admin()->create();
        ProjectMember::factory()->for($this->project)->for($admin)->create(['role' => ProjectRole::Buyer]);
        $this->notify($admin, PackageAssignment::Buyer);
        ProjectMember::query()->where('user_id', $admin->id)->delete();

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.total', 1)
                ->where('unreadNotificationCount', 1)
            );
    }

    public function test_the_list_is_paginated()
    {
        foreach (range(1, 26) as $number) {
            $this->notify($this->buyer, PackageAssignment::Buyer);
        }

        $this->actingAs($this->buyer)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->has('notifications.data', 25)
                ->where('notifications.total', 26)
                ->where('notifications.next_page_url', route('notifications.index', ['page' => 2]))
            );

        $this->actingAs($this->buyer)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
    }

    public function test_every_page_shares_the_unread_count_of_the_persons_own_notifications()
    {
        $this->notify($this->buyer, PackageAssignment::Owner);
        $this->notify($this->buyer, PackageAssignment::Buyer);
        $this->notify($this->buyer, PackageAssignment::Buyer)->markAsRead();
        $someoneElse = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($someoneElse)->create(['role' => ProjectRole::Buyer]);
        $this->notify($someoneElse, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('unreadNotificationCount', 2));
    }

    public function test_opening_a_notification_marks_it_as_read_and_goes_to_its_package()
    {
        $notification = $this->notify($this->buyer, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)
            ->get(route('notifications.show', $notification->id))
            ->assertRedirect(route('projects.packages.show', [$this->project, $this->package]));

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_a_notification_is_marked_as_read()
    {
        $notification = $this->notify($this->buyer, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)
            ->from(route('notifications.index'))
            ->patch(route('notifications.update', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()?->read_at);

        $this->actingAs($this->buyer)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page->where('unreadNotificationCount', 0));
    }

    public function test_someone_elses_notification_is_not_found()
    {
        $someoneElse = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($someoneElse)->create(['role' => ProjectRole::Buyer]);
        $notification = $this->notify($someoneElse, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)->get(route('notifications.show', $notification->id))->assertNotFound();
        $this->actingAs($this->buyer)->patch(route('notifications.update', $notification->id))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->patch(route('notifications.update', $notification->id))->assertNotFound();

        $this->assertNull($notification->fresh()?->read_at);
    }

    public function test_a_notification_of_a_project_the_person_has_left_is_not_found()
    {
        $notification = $this->notify($this->buyer, PackageAssignment::Buyer);
        ProjectMember::query()->where('user_id', $this->buyer->id)->delete();

        $this->actingAs($this->buyer)->patch(route('notifications.update', $notification->id))->assertNotFound();
        $this->actingAs($this->buyer)->get(route('notifications.show', $notification->id))->assertNotFound();
    }

    public function test_a_notification_address_must_hold_a_uuid()
    {
        $this->actingAs($this->buyer)->patch('/notifications/1')->assertNotFound();
        $this->actingAs($this->buyer)->patch('/notifications/'.Str::uuid())->assertNotFound();
    }

    public function test_marking_all_as_read_changes_only_the_persons_own_notifications()
    {
        $this->notify($this->buyer, PackageAssignment::Owner);
        $this->notify($this->buyer, PackageAssignment::Buyer);
        $someoneElse = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($someoneElse)->create(['role' => ProjectRole::Buyer]);
        $othersNotification = $this->notify($someoneElse, PackageAssignment::Buyer);

        $this->actingAs($this->buyer)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $this->buyer->unreadNotifications()->count());
        $this->assertNull($othersNotification->fresh()?->read_at);
    }

    public function test_guests_are_sent_to_sign_in()
    {
        $notification = $this->notify($this->buyer, PackageAssignment::Buyer);

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->get(route('notifications.show', $notification->id))->assertRedirect(route('login'));
        $this->patch(route('notifications.update', $notification->id))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    private function notify(User $user, PackageAssignment $assignment, ?Package $package = null): Notification
    {
        $earlier = Notification::query()->pluck('id');

        $user->notify(new PackageAssigned($package ?? $this->package, $assignment));

        return Notification::query()->whereNotIn('id', $earlier)->sole();
    }
}
