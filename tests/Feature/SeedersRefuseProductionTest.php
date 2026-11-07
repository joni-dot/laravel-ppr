<?php

namespace Tests\Feature;

use Database\Seeders\BrowserTestSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoProjectSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SeedersRefuseProductionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  class-string<Seeder>  $seeder
     */
    #[DataProvider('seeders')]
    public function test_the_seeder_adds_nothing_in_production(string $seeder): void
    {
        $this->app['env'] = 'production';

        $this->assertThrows(
            fn () => $this->app->call([$this->app->make($seeder), 'run']),
            RuntimeException::class,
            'production',
        );

        $this->assertDatabaseEmpty('users');
    }

    /**
     * @return array<string, array{class-string<Seeder>}>
     */
    public static function seeders(): array
    {
        return [
            'default admin' => [DatabaseSeeder::class],
            'browser test data' => [BrowserTestSeeder::class],
            'demo project' => [DemoProjectSeeder::class],
        ];
    }
}
