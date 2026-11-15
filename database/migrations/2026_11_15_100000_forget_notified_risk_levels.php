<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overdue now counts from a milestone's forecast or planned date, and below a closed or cancelled package
 * nothing is at risk. That changes many packages' risk levels and due dates at once, but nothing happened
 * to the packages themselves, so nobody should be told:
 *
 * - Forgetting the level people were last told about makes the next check of every package a first check:
 *   it only stores the level, and records the dates that are already overdue as reminded without telling
 *   anyone (NotifyRiskChanges, SendDateReminders).
 * - Reminders were recorded by expected date and are now recorded by due date, so a coming date someone was
 *   already reminded of would look new. Each such reminder about a date still to come is recorded again for
 *   the milestone's due date. A coming date nobody was reminded of is still news and is sent as usual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')->whereNotNull('notified_risk_level')->update(['notified_risk_level' => null]);

        DB::statement(<<<'SQL'
            insert into sent_reminders (user_id, package_id, project_milestone_id, kind, due_date, created_at)
            select reminders.user_id, reminders.package_id, reminders.project_milestone_id, reminders.kind,
                coalesce(dates.forecast_date, dates.planned_date), now()
            from sent_reminders as reminders
            join package_milestones as dates
                on dates.package_id = reminders.package_id
                and dates.project_milestone_id = reminders.project_milestone_id
            where reminders.kind = 'coming_up'
                and reminders.due_date >= ?::date
                and coalesce(dates.forecast_date, dates.planned_date) is not null
            on conflict (user_id, package_id, project_milestone_id, kind, due_date) do nothing
            SQL, [now('UTC')->toDateString()]);
    }

    public function down(): void
    {
        // Nothing to undo: the old levels are gone, and by now the checker has stored the levels under the new
        // rules. Putting back levels from before would tell people about changes that never happened. The extra
        // reminder records only stop repeats and are harmless under either rule.
    }
};
