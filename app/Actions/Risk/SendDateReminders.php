<?php

namespace App\Actions\Risk;

use App\Enums\DateReminderKind;
use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\User;
use App\Notifications\DateComingUp;
use App\Notifications\DateOverdue;
use App\Notifications\PackageNotification;
use App\Notifications\ProjectRecipients;
use App\Scheduling\Days;
use App\Scheduling\ProjectSchedules;
use App\Scheduling\ScheduledMilestone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Reminds a package's owner and buyer of its milestone dates: when an expected date comes within their
 * own notification period, and when it has passed without the milestone being marked done. Closed and
 * cancelled packages, done milestones and milestones without a date get none.
 *
 * Each reminder is sent once per person, package, milestone, kind and expected date (sent_reminders), so
 * a moved date gets a reminder of its own. A package checked for the first time has its overdue dates
 * recorded as reminded without telling anyone: its red risk badge already shows them, and the day the
 * feature goes live mustn't bring a flood of old news.
 */
class SendDateReminders
{
    /**
     * @param  Collection<int, stdClass>  $rows  ProjectRisks::packageRows() with notified_risk_level, as read before this run changed anything
     * @param  bool  $onlyFirstChecks  true after a save: only records the overdue dates of packages checked for the first time,
     *                                 so people aren't reminded of a date they have just typed, and the next daily run doesn't flood
     */
    public function handle(Project $project, Collection $rows, ProjectSchedules $schedules, bool $onlyFirstChecks = false): void
    {
        $recipients = ProjectRecipients::of($project);
        $today = Days::fromDate(now('UTC')->toDateString());
        $reminders = [];

        foreach ($rows as $row) {
            $isFirstCheck = $row->notified_risk_level === null;

            if (($onlyFirstChecks && ! $isFirstCheck) || $this->isFinished($row)) {
                continue;
            }

            foreach ($schedules->of($row->id)->milestones() as $milestone) {
                foreach ($recipients->among([$row->owner_id, $row->buyer_id]) as $person) {
                    $reminder = $this->reminder($row->id, $milestone, $person, $today, quiet: $isFirstCheck);

                    if ($reminder !== null && ($reminder['quiet'] || ! $onlyFirstChecks)) {
                        $reminders[$this->key($reminder)] = $reminder;
                    }
                }
            }
        }

        $this->send($project, $this->recordNew($reminders), $recipients, $schedules);
    }

    private function isFinished(stdClass $row): bool
    {
        return $row->status === PackageStatus::Closed->value || $row->status === PackageStatus::Cancelled->value;
    }

    /**
     * @return array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string, days_left: int, quiet: bool}|null
     */
    private function reminder(int $packageId, ScheduledMilestone $milestone, User $person, int $today, bool $quiet): ?array
    {
        if ($milestone->isDone() || $milestone->expected === null) {
            return null;
        }

        $daysLeft = Days::fromDate($milestone->expected) - $today;
        $kind = DateReminderKind::forDaysLeft($daysLeft, $person->notification_days);

        if ($kind === null) {
            return null;
        }

        return [
            'user_id' => $person->id,
            'package_id' => $packageId,
            'project_milestone_id' => $milestone->milestoneId,
            'kind' => $kind->value,
            'due_date' => $milestone->expected,
            'days_left' => $daysLeft,
            // Only overdue dates are kept quiet on a first check; a date coming up is still news.
            'quiet' => $quiet && $kind === DateReminderKind::Overdue,
        ];
    }

    /**
     * @param  array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string}|stdClass  $reminder
     */
    private function key(array|stdClass $reminder): string
    {
        $reminder = (array) $reminder;

        return implode('|', [$reminder['user_id'], $reminder['package_id'], $reminder['project_milestone_id'], $reminder['kind'], $reminder['due_date']]);
    }

    /**
     * Records the reminders in one statement. A reminder already recorded, by an earlier run or by one
     * running at the same moment, isn't returned, so only one run ever sends it.
     *
     * @param  array<string, array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string, days_left: int, quiet: bool}>  $reminders  per key()
     * @return list<array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string, days_left: int, quiet: bool}> the ones recorded now
     */
    private function recordNew(array $reminders): array
    {
        if ($reminders === []) {
            return [];
        }

        $recorded = DB::select(<<<'SQL'
            insert into sent_reminders (user_id, package_id, project_milestone_id, kind, due_date, created_at)
            select reminders.user_id, reminders.package_id, reminders.project_milestone_id, reminders.kind, reminders.due_date, now()
            from json_to_recordset(?::json) as reminders(user_id bigint, package_id bigint, project_milestone_id bigint, kind text, due_date date)
            on conflict (user_id, package_id, project_milestone_id, kind, due_date) do nothing
            returning user_id, package_id, project_milestone_id, kind, due_date
            SQL, [json_encode(array_values($reminders), JSON_THROW_ON_ERROR)], useReadPdo: false);

        return array_values(array_map(fn (stdClass $row) => $reminders[$this->key($row)], $recorded));
    }

    /**
     * @param  list<array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string, days_left: int, quiet: bool}>  $recorded
     */
    private function send(Project $project, array $recorded, ProjectRecipients $recipients, ProjectSchedules $schedules): void
    {
        $toSend = array_filter($recorded, fn (array $reminder) => ! $reminder['quiet']);

        if ($toSend === []) {
            return;
        }

        $packages = $project->packages()
            ->whereKey(array_unique(array_column($toSend, 'package_id')))
            ->get(['id', 'project_id', 'number', 'title'])
            ->keyBy('id');

        foreach ($toSend as $reminder) {
            [$person] = $recipients->among([$reminder['user_id']]);
            $person->notify($this->notification($packages[$reminder['package_id']], $reminder, $schedules));
        }
    }

    /**
     * @param  array{user_id: int, package_id: int, project_milestone_id: int, kind: string, due_date: string, days_left: int, quiet: bool}  $reminder
     */
    private function notification(Package $package, array $reminder, ProjectSchedules $schedules): PackageNotification
    {
        $milestoneName = $schedules->milestoneName($reminder['project_milestone_id']);

        return match (DateReminderKind::from($reminder['kind'])) {
            DateReminderKind::ComingUp => new DateComingUp($package, $milestoneName, $reminder['due_date'], $reminder['days_left']),
            DateReminderKind::Overdue => new DateOverdue($package, $milestoneName, $reminder['due_date']),
        };
    }
}
