<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_admin_outside_production(): void
    {
        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_it_refuses_to_create_the_default_admin_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertThrows(
            fn () => app(DatabaseSeeder::class)->run(),
            RuntimeException::class,
        );

        $this->assertDatabaseEmpty('users');
    }
}
