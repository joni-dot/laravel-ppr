<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Starting data for the browser tests in tests/Browser. They read these values
 * from tests/Browser/support/test-data.ts, so change both together.
 */
class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Browser test data must never be added to production.');
        }

        User::factory()->admin()->create(['name' => 'Browser Admin', 'email' => 'admin@browser-test.example']);
        $member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@browser-test.example']);
        $manager = User::factory()->create(['name' => 'Browser Manager', 'email' => 'manager@browser-test.example']);

        // The database is emptied first, so these get ids 1 and 2.
        Project::factory()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->withMember($member, ProjectRole::Viewer)
            ->create(['name' => 'Browser Project', 'code' => 'BP1']);
        Project::factory()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->create(['name' => 'Hidden Project', 'code' => 'HP2']);
    }
}
