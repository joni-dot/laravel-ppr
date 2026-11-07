<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepareBrowserTestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_erase_any_other_database(): void
    {
        $user = User::factory()->create();
        config(['database.connections.pgsql.database' => 'ppr']);

        $this->artisan('app:prepare-browser-tests')->assertFailed();

        $this->assertModelExists($user);
    }

    public function test_it_refuses_to_run_outside_the_testing_environment(): void
    {
        $user = User::factory()->create();
        $this->app['env'] = 'local';

        $this->artisan('app:prepare-browser-tests')->assertFailed();

        $this->assertModelExists($user);
    }
}
