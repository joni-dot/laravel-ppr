<?php

namespace Database\Seeders;

use App\Actions\Packages\CreatePackage;
use App\Enums\ProjectRole;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
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

        User::factory()->admin()->create(['name' => 'Browser Admin', 'email' => 'admin@browser-test.example']);
        $member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@browser-test.example']);
        $manager = User::factory()->create(['name' => 'Browser Manager', 'email' => 'manager@browser-test.example']);

        // The database is emptied first, so the projects get ids 1 to 4.
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
    }
}
