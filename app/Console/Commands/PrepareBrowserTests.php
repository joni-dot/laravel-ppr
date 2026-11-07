<?php

namespace App\Console\Commands;

use Database\Seeders\BrowserTestSeeder;
use Illuminate\Console\Command;

class PrepareBrowserTests extends Command
{
    protected $signature = 'app:prepare-browser-tests';

    protected $description = 'Empty the testing database and fill it with the browser test data';

    public function handle(): int
    {
        if (! $this->isUsingTheTestingDatabase()) {
            $this->components->error('Refusing to continue: this erases the database, so it only runs in the testing environment on the "testing" database.');

            return self::FAILURE;
        }

        return $this->call('migrate:fresh', [
            '--force' => true,
            '--seed' => true,
            '--seeder' => BrowserTestSeeder::class,
        ]);
    }

    private function isUsingTheTestingDatabase(): bool
    {
        $connection = config('database.default');

        return app()->environment('testing')
            && config("database.connections.{$connection}.database") === 'testing';
    }
}
