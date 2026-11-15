<?php

namespace Tests\Feature\Risk;

use App\Actions\Packages\CreatePackage;
use App\Actions\Risk\NotifyRiskChanges;
use App\Enums\ProjectRole;
use App\Jobs\CheckProjectRisks;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use App\Notifications\DateComingUp;
use App\Notifications\DateOverdue;
use App\Notifications\RiskLevelChanged;
use App\Risk\ProjectRisks;
use App\Scheduling\ProjectSchedules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RiskChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $owner;

    private User $buyer;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->withMember($this->owner, ProjectRole::PackageOwner)
            ->withMember($this->buyer, ProjectRole::Buyer)
            ->create(['risk_amber_days' => 14, 'risk_red_days' => 0]);
        $this->package = $this->addPackage();
    }

    public function test_the_first_check_only_stores_the_level()
    {
        $this->check();

        $this->assertSame('none', $this->package->fresh()->notified_risk_level);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_a_changed_level_is_told_to_the_project_managers_and_the_package_owner()
    {
        $this->check();
        $this->makeLate();

        $this->check();

        $notifications = Notification::query()->where('type', 'risk_changed')->get();
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->owner->id], $notifications->pluck('notifiable_id')->all());
        $this->assertEquals([
            'from' => 'none',
            'to' => 'red',
            'reasons' => ['late'],
            'float_days' => -19,
            'overdue_milestone' => null,
            'overdue_date' => null,
        ], $notifications->first()->data['params']);
        $this->assertSame('red', $this->package->fresh()->notified_risk_level);
    }

    public function test_an_unchanged_level_is_not_told_again()
    {
        $this->check();
        $this->makeLate();
        $this->check();

        $this->check();

        $this->assertSame(2, Notification::query()->count());
    }

    public function test_the_person_who_made_the_change_is_not_told()
    {
        $this->check();
        $this->makeLate();

        $this->check(changedBy: $this->manager);

        $this->assertSame([$this->owner->id], Notification::query()->pluck('notifiable_id')->all());
    }

    public function test_the_person_who_saved_is_still_told_about_changes_their_save_did_not_cause()
    {
        $other = $this->addPackage();
        $this->check();
        // Turned red overnight, before anyone's save started a check.
        $this->makeLate($other);

        $this->saveLateDates($this->package, savedBy: $this->manager);

        $this->assertSame([$other->id], $this->toldAbout($this->manager));
        $this->assertEqualsCanonicalizing([$this->package->id, $other->id], $this->toldAbout($this->owner));
    }

    public function test_the_person_who_saved_a_sub_package_is_not_told_about_the_packages_above_it()
    {
        $child = $this->addPackage($this->package);
        $grandchild = $this->addPackage($child);
        $this->check();

        $this->saveLateDates($grandchild, savedBy: $this->manager);

        $this->assertSame([], $this->toldAbout($this->manager));
        $this->assertEqualsCanonicalizing([$this->package->id, $child->id, $grandchild->id], $this->toldAbout($this->owner));
    }

    public function test_the_person_who_cancelled_a_package_is_not_told_about_the_packages_below_or_above_it()
    {
        $child = $this->addPackage($this->package);
        $grandchild = $this->addPackage($child);
        $other = $this->addPackage();
        $this->makeLate($grandchild);
        $this->check();
        $this->makeLate($other);

        $this->actingAs($this->manager)
            ->put(route('projects.packages.update', [$this->project, $child]), ['title' => $child->title, 'status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame([$other->id], $this->toldAbout($this->manager));
        $this->assertEqualsCanonicalizing([$this->package->id, $child->id, $grandchild->id, $other->id], $this->toldAbout($this->owner));
    }

    public function test_a_save_that_changes_the_whole_project_tells_the_saver_nothing()
    {
        $other = $this->addPackage();
        $this->check();
        $this->makeLate($other);

        $this->actingAs($this->manager)->put(route('projects.update', $this->project), [
            'name' => $this->project->name,
            'code' => $this->project->code,
            'risk_amber_days' => 30,
            'risk_red_days' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $this->toldAbout($this->manager));
        $this->assertSame([$other->id], $this->toldAbout($this->owner));
    }

    public function test_the_daily_check_leaves_nobody_out()
    {
        $this->check();
        $this->makeLate();

        $this->check(daily: true);

        $this->assertSame([$this->package->id], $this->toldAbout($this->manager));
        $this->assertSame([$this->package->id], $this->toldAbout($this->owner));
    }

    public function test_deactivated_people_and_former_members_are_not_told()
    {
        $otherManager = User::factory()->create();
        $this->project->members()->make(['role' => ProjectRole::ProjectManager])->forceFill(['user_id' => $otherManager->id])->save();
        $this->check();
        $this->makeLate();
        $this->owner->forceFill(['deactivated_at' => now()])->save();
        $this->project->members()->where('user_id', $this->manager->id)->delete();

        $this->check();

        $this->assertSame([$otherManager->id], Notification::query()->pluck('notifiable_id')->all());
    }

    public function test_a_project_manager_who_owns_the_package_is_told_once()
    {
        $this->package->forceFill(['owner_id' => $this->manager->id])->save();
        $this->check();
        $this->makeLate();

        $this->check();

        $this->assertSame([$this->manager->id], Notification::query()->pluck('notifiable_id')->all());
    }

    public function test_a_level_another_check_has_already_stored_is_not_told_twice()
    {
        $this->check();
        $this->makeLate();
        $rowsReadBeforeTheOtherCheck = $this->rows();

        // Another check, running at the same moment, has stored the new level and told everyone.
        $this->check();
        $this->assertSame(2, Notification::query()->count());

        app(NotifyRiskChanges::class)->handle($this->project, $rowsReadBeforeTheOtherCheck, ProjectSchedules::for($this->project, $rowsReadBeforeTheOtherCheck, now('UTC')->toDateString()), null);

        $this->assertSame(2, Notification::query()->count());
    }

    public function test_saving_a_late_date_tells_the_others_once_the_save_is_done()
    {
        $this->check();
        $milestone = $this->project->milestones()->where('name', 'Required on site')->firstOrFail();

        $this->actingAs($this->manager)->put(route('projects.packages.schedule.update', [$this->project, $this->package]), [
            'milestones' => [['id' => $milestone->id, 'planned_date' => '2027-12-01', 'forecast_date' => '2027-12-20', 'actual_date' => null, 'lead_days' => null]],
        ])->assertSessionHasNoErrors();

        $notification = Notification::query()->sole();
        $this->assertSame($this->owner->id, $notification->notifiable_id);
        $this->assertSame('risk_changed', $notification->type);
    }

    public function test_a_check_takes_the_same_queries_however_many_packages_change()
    {
        // Each notification is sent by its own queued job, which checks again that the person may still see it.
        Queue::fake();
        $this->addBusyPackage();
        $this->check();
        $this->travel(1)->days();
        $queriesForOne = $this->countQueries(fn () => $this->check(daily: true));

        foreach (range(1, 8) as $count) {
            $this->addBusyPackage();
        }
        $this->check();
        $this->travel(1)->days();

        $this->assertSame($queriesForOne, $this->countQueries(fn () => $this->check(daily: true)));
        $this->assertSame(10, $queriesForOne);
        foreach ([RiskLevelChanged::class, DateOverdue::class, DateComingUp::class] as $kind) {
            $this->assertCount(2 + 8 * 2, Queue::pushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->notification instanceof $kind));
        }
    }

    /**
     * Tomorrow it turns red, one date is overdue and another is coming up.
     */
    private function addBusyPackage(): void
    {
        $package = $this->addPackage();
        $this->fillIn($package, 'Order placed', ['planned_date' => now()->toDateString()]);
        $this->fillIn($package, 'Delivered from supplier', ['planned_date' => now()->addDays(3)->toDateString()]);
    }

    private function addPackage(?Package $parent = null): Package
    {
        $package = $parent === null
            ? Package::factory()->for($this->project)->create()
            : app(CreatePackage::class)->handle($this->project->fresh(), 'Sub-package', null, $parent);
        $package->forceFill(['owner_id' => $this->owner->id, 'buyer_id' => $this->buyer->id, 'status' => 'ordered'])->save();
        $this->fillIn($package, 'Required on site', ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-01']);

        return $package;
    }

    private function makeLate(?Package $package = null): void
    {
        $milestone = $this->project->milestones()->where('name', 'Required on site')->firstOrFail();
        PackageMilestone::query()->whereBelongsTo($package ?? $this->package)->whereBelongsTo($milestone)->update(['forecast_date' => '2027-12-20']);
    }

    private function saveLateDates(Package $package, User $savedBy): void
    {
        $milestone = $this->project->milestones()->where('name', 'Required on site')->firstOrFail();

        $this->actingAs($savedBy)->put(route('projects.packages.schedule.update', [$this->project, $package]), [
            'milestones' => [['id' => $milestone->id, 'planned_date' => '2027-12-01', 'forecast_date' => '2027-12-20', 'actual_date' => null, 'lead_days' => null]],
        ])->assertSessionHasNoErrors();
    }

    /**
     * @return list<int> the packages the person got a risk-change notification about
     */
    private function toldAbout(User $person): array
    {
        return Notification::query()
            ->where('notifiable_id', $person->id)
            ->where('type', 'risk_changed')
            ->get()
            ->map(fn (Notification $notification) => $notification->data['package_id'])
            ->all();
    }

    /**
     * @param  array<string, string|int>  $values
     */
    private function fillIn(Package $package, string $milestoneName, array $values): void
    {
        $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();

        PackageMilestone::factory()->for($package)->for($milestone)->create($values);
    }

    private function check(?User $changedBy = null, bool $daily = false): void
    {
        // Run directly rather than through the queue, which some tests fake to catch the notifications.
        app()->call([new CheckProjectRisks($this->project->id, $changedBy?->id, $daily), 'handle']);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function rows(): Collection
    {
        return ProjectRisks::packageRows($this->project)->addSelect('packages.notified_risk_level')->get();
    }

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
