<?php

namespace Database\Seeders;

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
        User::factory()->create(['name' => 'Browser Member', 'email' => 'member@browser-test.example']);
    }
}
