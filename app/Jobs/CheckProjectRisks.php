<?php

namespace App\Jobs;

use App\Actions\Risk\NotifyRiskChanges;
use App\Actions\Risk\SendDateReminders;
use App\Models\Project;
use App\Models\User;
use App\Risk\ProjectRisks;
use App\Scheduling\ProjectSchedules;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks every package of one project in the background: tells people about changed risk levels and,
 * on the daily run, about coming and overdue milestone dates. Dispatched after every save that can
 * change a package's risk, and once a day for every project (packages:check-risks).
 *
 * The same number of queries however many packages the project has. Two runs at the same time are
 * safe: each notification goes out only from the run whose database write actually took effect.
 */
class CheckProjectRisks implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int|null  $changedById  whose save started the check; they aren't told about their own change
     * @param  bool  $sendDateReminders  only on the daily run, so nobody is reminded of a date they have just typed
     */
    public function __construct(
        public readonly int $projectId,
        public readonly ?int $changedById = null,
        public readonly bool $sendDateReminders = false,
    ) {
        // A save's check must see what the save wrote.
        $this->afterCommit();
    }

    /**
     * For a change to a person that matters in every project they belong to, such as deactivating them:
     * a package whose owner or buyer is deactivated is at risk.
     */
    public static function dispatchForProjectsOf(User $person, ?int $changedById): void
    {
        foreach ($person->projectMemberships()->pluck('project_id') as $projectId) {
            self::dispatch($projectId, $changedById);
        }
    }

    public function handle(NotifyRiskChanges $notifyRiskChanges, SendDateReminders $sendDateReminders): void
    {
        $project = Project::query()->find($this->projectId);

        if ($project === null) {
            return;
        }

        // Read once for both: the stored levels as they were before this run tells the reminders which packages are new.
        $rows = ProjectRisks::packageRows($project)->addSelect('packages.notified_risk_level')->get();
        $schedules = ProjectSchedules::for($project, $rows);

        $notifyRiskChanges->handle($project, $rows, $schedules, $this->changedById);
        $sendDateReminders->handle($project, $rows, $schedules, onlyFirstChecks: ! $this->sendDateReminders);
    }
}
