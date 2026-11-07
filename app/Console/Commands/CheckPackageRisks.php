<?php

namespace App\Console\Commands;

use App\Jobs\CheckProjectRisks;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * The daily check: risk changes caused by time passing, and reminders of coming and overdue dates.
 * One background job per project, so a big project doesn't hold up the others.
 */
class CheckPackageRisks extends Command
{
    protected $signature = 'packages:check-risks';

    protected $description = 'Queue the daily risk and date reminder check of every project';

    public function handle(): int
    {
        $projectIds = Project::query()->orderBy('id')->pluck('id');

        foreach ($projectIds as $projectId) {
            CheckProjectRisks::dispatch($projectId, sendDateReminders: true);
        }

        $this->info("Queued the check of {$projectIds->count()} projects.");

        return self::SUCCESS;
    }
}
