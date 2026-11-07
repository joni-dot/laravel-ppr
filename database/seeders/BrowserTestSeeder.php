<?php

namespace Database\Seeders;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Jobs\CheckProjectRisks;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Starting data for the browser tests in tests/Browser. They read these values
 * from tests/Browser/support/test-data.ts, so change both together.
 */
class BrowserTestSeeder extends Seeder
{
    public function __construct(private readonly CreatePackage $createPackage) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Browser test data must never be added to production.');
        }

        $admin = User::factory()->admin()->create(['name' => 'Browser Admin', 'email' => 'admin@browser-test.example']);
        $member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@browser-test.example']);
        $manager = User::factory()->create(['name' => 'Browser Manager', 'email' => 'manager@browser-test.example']);

        // The database is emptied first, so the projects get ids 1 to 7.
        Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Viewer)
            ->create(['name' => 'Browser Project', 'code' => 'BP1']);
        Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->create(['name' => 'Hidden Project', 'code' => 'HP2']);

        // Its own project, so the package tests don't depend on the numbering the project tests change.
        $packageProject = Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Viewer)
            ->create(['name' => 'Package Project', 'code' => 'PP3']);
        ProjectCategory::factory()->for($packageProject)->create(['code' => 'ELE', 'name' => 'Electrical']);
        $mechanical = ProjectCategory::factory()->for($packageProject)->create(['code' => 'MEC', 'name' => 'Mechanical']);
        $seededPump = $this->createPackage->handle($packageProject, 'Seeded pump', $mechanical);
        // After the need date, so the package list's next milestone and float stay empty for the other tests.
        PackageMilestone::factory()->for($seededPump)->create([
            'project_milestone_id' => $packageProject->milestones()->where('name', 'Installed / commissioned')->value('id'),
            'forecast_date' => '2027-09-30',
        ]);

        // Its own project too, so changing the milestone list doesn't disturb the package tests.
        Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Viewer)
            ->create(['name' => 'Milestone Project', 'code' => 'MP4']);

        // For the notification tests: the member is a buyer here, so the manager can make them a package's buyer.
        $notificationProject = Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Buyer)
            ->create(['name' => 'Notification Project', 'code' => 'NP5']);
        $this->createPackage->handle(
            $notificationProject,
            'Notified valve',
            ProjectCategory::factory()->for($notificationProject)->create(['code' => 'MEC', 'name' => 'Mechanical']),
        );

        $this->addRiskProject($manager, $member);
        $this->addRiskCheckProject($manager, $member, $admin);
    }

    /**
     * For the risk check tests: the member owns a package with plenty of time, which the manager makes late
     * in the test. The admin owns one with a date overdue and one coming up, as if the feature had been
     * running for a while and the daily check had then found them, so the admin starts with those reminders.
     */
    private function addRiskCheckProject(User $manager, User $member, User $admin): void
    {
        $project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::PackageOwner)
            ->withMember($admin, ProjectRole::PackageOwner)
            ->create(['name' => 'Risk Check Project', 'code' => 'RC7', 'number_includes_category' => false]);
        $milestoneIds = $project->milestones()->pluck('id', 'name');
        $plenty = ['Required on site' => ['planned_date' => '2099-06-01', 'forecast_date' => '2099-01-01']];

        $watched = $this->addRiskPackage($project, 'Watched tank', null, $manager, $milestoneIds, $plenty);
        $watched->forceFill(['owner_id' => $member->id])->save();
        $reminded = $this->addRiskPackage($project, 'Reminded compressor', null, $manager, $milestoneIds, $plenty);
        $reminded->forceFill(['owner_id' => $admin->id])->save();

        // The first check only stores the levels; overdue dates found then would be kept quiet.
        CheckProjectRisks::dispatchSync($project->id);

        $today = now('UTC');
        PackageMilestone::factory()->for($reminded)->create(['project_milestone_id' => $milestoneIds['Order placed'], 'planned_date' => $today->copy()->subDays(2)->toDateString()]);
        PackageMilestone::factory()->for($reminded)->create(['project_milestone_id' => $milestoneIds['Delivered from supplier'], 'planned_date' => $today->copy()->addDays(3)->toDateString()]);
        CheckProjectRisks::dispatchSync($project->id, sendDateReminders: true);
    }

    /**
     * For the risk tests: one package at each level, and a red one under a package that is fine, to see the
     * list's risk filter keep the packages above a match. Dates are far in the past or future, so the levels
     * don't change as the days go by. The member is a viewer here.
     */
    private function addRiskProject(User $manager, User $member): void
    {
        $project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Viewer)
            ->create(['name' => 'Risk Project', 'code' => 'RP6', 'number_includes_category' => false]);
        $milestoneIds = $project->milestones()->pluck('id', 'name');
        $plenty = ['Required on site' => ['planned_date' => '2099-06-01', 'forecast_date' => '2099-01-01']];

        $skid = $this->addRiskPackage($project, 'Main skid', null, $manager, $milestoneIds, $plenty);
        $this->addRiskPackage($project, 'Overdue pump', $skid, $manager, $milestoneIds, [
            ...$plenty,
            'Order placed' => ['planned_date' => '2020-01-15'],
        ]);
        $this->addRiskPackage($project, 'Unowned crate', null, null, $milestoneIds, $plenty);
        $this->addRiskPackage($project, 'Calm valve', null, $manager, $milestoneIds, $plenty);
    }

    /**
     * @param  Collection<string, int>  $milestoneIds
     * @param  array<string, array<string, string>>  $dates  per milestone name
     */
    private function addRiskPackage(Project $project, string $title, ?Package $parent, ?User $ownerAndBuyer, Collection $milestoneIds, array $dates): Package
    {
        $package = $this->createPackage->handle($project, $title, null, $parent);
        $package->forceFill(['owner_id' => $ownerAndBuyer?->id, 'buyer_id' => $ownerAndBuyer?->id, 'status' => PackageStatus::Ordered])->save();

        foreach ($dates as $milestone => $values) {
            PackageMilestone::factory()->for($package)->create(['project_milestone_id' => $milestoneIds[$milestone], ...$values]);
        }

        return $package;
    }
}
