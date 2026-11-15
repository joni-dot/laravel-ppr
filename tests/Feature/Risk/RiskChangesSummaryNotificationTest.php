<?php

namespace Tests\Feature\Risk;

use App\Actions\Risk\NotifyRiskChanges;
use App\Enums\ProjectRole;
use App\Jobs\CheckProjectRisks;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Notifications\RiskChangesSummary;
use App\Notifications\RiskLevelChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * When one check would send a person more than ten risk-change notices, they get one summary instead.
 */
class RiskChangesSummaryNotificationTest extends TestCase
{
    use RefreshDatabase;

    // Forecasts for "Required on site", planned on 2027-12-01, with an amber warning of 14 days.
    private const string NO_RISK = '2027-06-01';

    private const string AMBER = '2027-11-25';

    private const string RED = '2027-12-20';

    private User $manager;

    private User $owner;

    private User $buyer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->project = $this->createProject([$this->manager], [$this->owner]);
    }

    public function test_more_than_ten_changes_for_one_person_come_as_one_summary()
    {
        $wasRed = $this->addPackages(3, forecast: self::RED);
        $nowRed = $this->addPackages(4);
        $nowAmber = $this->addPackages(4);
        $this->check();
        $this->setForecast($wasRed, self::NO_RISK);
        $this->setForecast($nowRed, self::RED);
        $this->setForecast($nowAmber, self::AMBER);

        $this->check();

        $summaries = Notification::query()->where('type', 'risk_changes_summary')->get();
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->owner->id], $summaries->pluck('notifiable_id')->all());
        $this->assertEquals(['params' => ['changed' => 11, 'red' => 4, 'amber' => 4, 'none' => 3]], $summaries->first()->data);
        $this->assertSame($this->project->id, $summaries->first()->project_id);
        $this->assertSame(0, Notification::query()->where('type', 'risk_changed')->count());
    }

    public function test_exactly_ten_changes_still_come_one_by_one()
    {
        $packages = $this->addPackages(NotifyRiskChanges::MOST_SINGLE_NOTICES);
        $this->check();
        $this->setForecast($packages, self::RED);

        $this->check();

        $this->assertSame(NotifyRiskChanges::MOST_SINGLE_NOTICES, $this->noticesOf($this->manager, 'risk_changed'));
        $this->assertSame(0, Notification::query()->where('type', 'risk_changes_summary')->count());
    }

    public function test_each_person_is_counted_on_their_own()
    {
        $otherOwner = User::factory()->create();
        $this->project->members()->make(['role' => ProjectRole::PackageOwner])->forceFill(['user_id' => $otherOwner->id])->save();
        $packages = $this->addPackages(5)->merge($this->addPackages(6, owner: $otherOwner));
        $this->check();
        $this->setForecast($packages, self::RED);

        $this->check();

        $this->assertSame(1, $this->noticesOf($this->manager, 'risk_changes_summary'));
        $this->assertSame(0, $this->noticesOf($this->manager, 'risk_changed'));
        $this->assertSame(5, $this->noticesOf($this->owner, 'risk_changed'));
        $this->assertSame(6, $this->noticesOf($otherOwner, 'risk_changed'));
    }

    public function test_after_a_summary_the_next_check_tells_one_by_one_again()
    {
        $packages = $this->addPackages(11);
        $this->check();
        $this->setForecast($packages, self::RED);
        $this->check();

        $this->setForecast($packages->take(1), self::NO_RISK);
        $this->check();

        $this->assertSame(1, $this->noticesOf($this->manager, 'risk_changes_summary'));
        $notice = Notification::query()->where('notifiable_id', $this->manager->id)->where('type', 'risk_changed')->sole();
        $this->assertSame($packages->first()->id, $notice->data['package_id']);
    }

    public function test_the_person_who_changed_the_risk_warnings_gets_no_summary()
    {
        $this->project->forceFill(['risk_amber_days' => 1])->save();
        $this->addPackages(11, forecast: self::AMBER);
        $this->check();

        $this->actingAs($this->manager)->put(route('projects.update', $this->project), [
            'name' => $this->project->name,
            'code' => $this->project->code,
            'risk_amber_days' => 14,
            'risk_red_days' => 0,
        ])->assertSessionHasNoErrors();

        $summary = Notification::query()->sole();
        $this->assertSame($this->owner->id, $summary->notifiable_id);
        $this->assertEquals(['changed' => 11, 'red' => 0, 'amber' => 11, 'none' => 0], $summary->data['params']);
    }

    public function test_deactivated_people_and_former_members_get_no_summary()
    {
        $formerManager = User::factory()->create();
        $deactivatedManager = User::factory()->create(['deactivated_at' => now()]);
        foreach ([$formerManager, $deactivatedManager] as $person) {
            $this->project->members()->make(['role' => ProjectRole::ProjectManager])->forceFill(['user_id' => $person->id])->save();
        }
        $packages = $this->addPackages(11);
        $this->check();
        $this->project->members()->where('user_id', $formerManager->id)->delete();
        $this->setForecast($packages, self::RED);

        $this->check();

        $this->assertEqualsCanonicalizing(
            [$this->manager->id, $this->owner->id],
            Notification::query()->where('type', 'risk_changes_summary')->pluck('notifiable_id')->all(),
        );
    }

    public function test_a_check_takes_the_same_queries_however_many_packages_and_people_it_tells()
    {
        // Each notification is sent by its own queued job, which checks again that the person may still see it.
        Queue::fake();
        $few = $this->busyProject(managers: 1, owners: 1, packagesPerOwner: 11);
        $many = $this->busyProject(managers: 3, owners: 4, packagesPerOwner: 15);

        $queriesForFew = $this->countQueries(fn () => $this->check($few));
        $queriesForMany = $this->countQueries(fn () => $this->check($many));

        $this->assertSame($queriesForFew, $queriesForMany);
        // The busy project's 3 managers and 4 owners each get one summary; its one-package owner one notice.
        $this->assertCount(1 + 1 + 3 + 4, Queue::pushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof RiskChangesSummary));
        $this->assertCount(2, Queue::pushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof RiskLevelChanged));
    }

    public function test_only_the_person_it_was_sent_to_opens_or_marks_a_summary()
    {
        $packages = $this->addPackages(11);
        $viewer = User::factory()->create();
        $this->project->members()->make(['role' => ProjectRole::Viewer])->forceFill(['user_id' => $viewer->id])->save();
        $admin = User::factory()->admin()->create();
        $this->check();
        $this->setForecast($packages, self::RED);
        $this->check();
        $summary = Notification::query()->where('notifiable_id', $this->owner->id)->sole();

        foreach ([$this->manager, $viewer, $admin] as $someoneElse) {
            $this->actingAs($someoneElse)->get(route('notifications.show', $summary))->assertNotFound();
            $this->actingAs($someoneElse)->patch(route('notifications.update', $summary))->assertNotFound();
        }
        $this->assertNull($summary->fresh()->read_at);

        $this->actingAs($this->owner)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.total', 1)
                ->where('notifications.data.0.kind', 'risk_changes_summary')
                ->where('notifications.data.0.projectName', $this->project->name)
                ->where('notifications.data.0.packageNumber', null)
                ->where('notifications.data.0.packageTitle', null)
                ->where('notifications.data.0.params', ['changed' => 11, 'red' => 11, 'amber' => 0, 'none' => 0])
            );
        $this->actingAs($this->owner)
            ->get(route('notifications.show', $summary))
            ->assertRedirect(route('projects.packages.index', $this->project));
        $this->assertNotNull($summary->fresh()->read_at);
    }

    /**
     * A project whose packages all turn red at its next check, with an extra owner of one package who gets a single notice.
     */
    private function busyProject(int $managers, int $owners, int $packagesPerOwner): Project
    {
        $ownerOfOne = User::factory()->create();
        $busyOwners = User::factory()->count($owners)->create();
        $this->project = $this->createProject(User::factory()->count($managers)->create()->all(), [$ownerOfOne, ...$busyOwners]);
        $packages = $this->addPackages(1, owner: $ownerOfOne);

        foreach ($busyOwners as $owner) {
            $packages = $packages->merge($this->addPackages($packagesPerOwner, owner: $owner));
        }

        $this->check();
        $this->setForecast($packages, self::RED);

        return $this->project;
    }

    /**
     * @param  list<User>  $managers
     * @param  list<User>  $owners
     */
    private function createProject(array $managers, array $owners): Project
    {
        $factory = Project::factory()->withDefaultMilestones()->withMember($this->buyer, ProjectRole::Buyer);

        foreach ($managers as $manager) {
            $factory = $factory->withMember($manager, ProjectRole::ProjectManager);
        }

        foreach ($owners as $owner) {
            $factory = $factory->withMember($owner, ProjectRole::PackageOwner);
        }

        return $factory->create(['risk_amber_days' => 14, 'risk_red_days' => 0]);
    }

    /**
     * @return Collection<int, Package>
     */
    private function addPackages(int $count, string $forecast = self::NO_RISK, ?User $owner = null): Collection
    {
        $packages = Package::factory()->for($this->project)->count($count)->create([
            'owner_id' => ($owner ?? $this->owner)->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'ordered',
        ]);
        $milestone = $this->requiredOnSite();

        foreach ($packages as $package) {
            PackageMilestone::factory()->for($package)->for($milestone)->create(['planned_date' => '2027-12-01', 'forecast_date' => $forecast]);
        }

        return $packages->toBase();
    }

    /**
     * @param  Collection<int, Package>  $packages
     */
    private function setForecast(Collection $packages, string $forecast): void
    {
        PackageMilestone::query()
            ->whereIn('package_id', $packages->pluck('id'))
            ->where('project_milestone_id', $this->requiredOnSite()->id)
            ->update(['forecast_date' => $forecast]);
    }

    private function requiredOnSite(): ProjectMilestone
    {
        return $this->project->milestones()->where('name', 'Required on site')->firstOrFail();
    }

    private function noticesOf(User $person, string $kind): int
    {
        return Notification::query()->where('notifiable_id', $person->id)->where('type', $kind)->count();
    }

    private function check(?Project $project = null): void
    {
        // Run directly rather than through the queue, which some tests fake to catch the notifications.
        app()->call([new CheckProjectRisks(($project ?? $this->project)->id), 'handle']);
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
