<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The default admin must never be added to production.');
        }

        // User::factory(10)->create();

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
