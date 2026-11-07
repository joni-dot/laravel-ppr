<?php

namespace Tests\Feature\Notifications;

use App\Actions\Packages\NotifyNewPackageAssignees;
use App\Enums\PackageAssignment;
use App\Enums\ProjectRole;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\PackageAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->project = Project::factory()->withMember($this->manager, ProjectRole::ProjectManager)->create(['number_includes_category' => false]);
        $this->package = Package::factory()->for($this->project)->create(['number' => 'PKG-0007', 'title' => 'Main transformer']);
    }

    public function test_a_new_owner_is_told_they_own_the_package()
    {
        $owner = $this->member(ProjectRole::PackageOwner);

        $this->assign(['owner_id' => $owner->id]);

        $notification = Notification::query()->sole();
        $this->assertSame($owner->id, $notification->notifiable_id);
        $this->assertSame('package_assigned', $notification->type);
        $this->assertSame($this->project->id, $notification->project_id);
        // assertEquals, because the database keeps JSON keys in its own order.
        $this->assertEquals([
            'package_id' => $this->package->id,
            'package_number' => 'PKG-0007',
            'package_title' => 'Main transformer',
            'params' => ['role' => 'owner'],
        ], $notification->data);
        $this->assertNull($notification->read_at);
    }

    public function test_a_new_buyer_is_told_they_buy_the_package()
    {
        $buyer = $this->member(ProjectRole::Buyer);

        $this->assign(['buyer_id' => $buyer->id]);

        $notification = Notification::query()->sole();
        $this->assertSame($buyer->id, $notification->notifiable_id);
        $this->assertSame(['role' => 'buyer'], $notification->data['params']);
    }

    public function test_someone_who_becomes_both_owner_and_buyer_hears_about_both()
    {
        $buyer = $this->member(ProjectRole::Buyer);

        $this->assign(['owner_id' => $buyer->id, 'buyer_id' => $buyer->id]);

        $this->assertEqualsCanonicalizing(
            [['role' => 'owner'], ['role' => 'buyer']],
            $buyer->notifications()->get()->pluck('data.params')->all(),
        );
    }

    public function test_people_are_not_told_about_assigning_themselves()
    {
        $this->assign(['owner_id' => $this->manager->id, 'buyer_id' => $this->manager->id]);

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_keeping_the_same_person_sends_nothing()
    {
        $buyer = $this->member(ProjectRole::Buyer);
        $this->package->forceFill(['owner_id' => $buyer->id, 'buyer_id' => $buyer->id])->save();

        $this->assign(['owner_id' => $buyer->id, 'buyer_id' => $buyer->id, 'title' => 'Renamed transformer']);

        $this->assertSame('Renamed transformer', $this->package->fresh()?->title);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_removing_a_person_sends_nothing()
    {
        $buyer = $this->member(ProjectRole::Buyer);
        $this->package->forceFill(['buyer_id' => $buyer->id])->save();

        $this->assign(['buyer_id' => null]);

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_deactivated_people_are_not_told()
    {
        $buyer = $this->member(ProjectRole::Buyer);
        $buyer->forceFill(['deactivated_at' => now()])->save();
        $this->package->forceFill(['buyer_id' => $buyer->id])->save();

        app(NotifyNewPackageAssignees::class)->handle($this->package, $this->manager);

        $this->assertTrue($this->package->wasChanged('buyer_id'));
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_nothing_is_sent_to_someone_deactivated_or_gone_from_the_project_before_it_goes_out()
    {
        $deactivated = $this->member(ProjectRole::Buyer);
        $deactivated->forceFill(['deactivated_at' => now()])->save();
        $leftTheProject = $this->member(ProjectRole::Buyer);
        ProjectMember::query()->where('user_id', $leftTheProject->id)->delete();

        $deactivated->notify(new PackageAssigned($this->package, PackageAssignment::Buyer));
        $leftTheProject->notify(new PackageAssigned($this->package, PackageAssignment::Buyer));

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_the_notification_is_sent_in_the_background()
    {
        $this->assertInstanceOf(ShouldQueue::class, new PackageAssigned($this->package, PackageAssignment::Owner));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assign(array $changes): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.packages.update', [$this->project, $this->package]), [
                'title' => $this->package->title,
                'status' => 'draft',
                ...$changes,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    private function member(ProjectRole $role): User
    {
        $user = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($user)->create(['role' => $role]);

        return $user;
    }
}
