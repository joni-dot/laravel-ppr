<?php

namespace Tests\Feature\Risk;

use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Jobs\CheckProjectRisks;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The release that changed the overdue rule forgets the risk levels people were told about, so the first
 * check after it is silent instead of telling everyone about levels and dates the new rules moved.
 */
class RiskRulesBaselineNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create();
        $this->owner = User::factory()->create(['notification_days' => 7]);
        $buyer = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->withMember($this->owner, ProjectRole::PackageOwner)
            ->withMember($buyer, ProjectRole::Buyer)
            ->create(['risk_amber_days' => 14, 'risk_red_days' => 0]);
    }

    public function test_the_first_check_after_the_release_tells_nobody_and_the_next_one_tells_as_usual()
    {
        // Told "no risk" under the old rules, but its planned order date has passed: red now.
        $nowOverdue = $this->addPackage(notifiedLevel: 'none');
        $this->fillIn($nowOverdue, 'Order placed', ['planned_date' => '2027-03-01']);
        $this->fillIn($nowOverdue, 'Delivered from supplier', ['planned_date' => '2027-03-10']);
        // Told "red" under the old rules; on time now.
        $nowFine = $this->addPackage(notifiedLevel: 'red');

        $this->forgetNotifiedRiskLevels();
        $this->checkDaily();

        $this->assertSame(0, Notification::query()->whereIn('type', ['risk_changed', 'risk_changes_summary', 'date_overdue'])->count());
        $this->assertSame('red', $nowOverdue->fresh()->notified_risk_level);
        $this->assertSame('none', $nowFine->fresh()->notified_risk_level);
        // The passed date counts as reminded, so the next run doesn't bring it up either.
        $this->assertTrue(DB::table('sent_reminders')->where('package_id', $nowOverdue->id)->where('kind', 'overdue')->where('due_date', '2027-03-01')->exists());

        $this->travel(1)->days();
        $this->makeLate($nowFine);
        $this->checkDaily();

        $overdue = Notification::query()->where('type', 'date_overdue')->where('notifiable_id', $this->owner->id)->sole();
        $this->assertEquals(['milestone' => 'Delivered from supplier', 'date' => '2027-03-10'], $overdue->data['params']);
        $riskChanges = Notification::query()->where('type', 'risk_changed')->get();
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->owner->id], $riskChanges->pluck('notifiable_id')->all());
        $this->assertSame([$nowFine->id], $riskChanges->pluck('data.package_id')->unique()->values()->all());
    }

    public function test_a_coming_date_already_reminded_of_under_the_old_rule_is_not_reminded_again()
    {
        $package = $this->addPackage(notifiedLevel: 'none');
        $this->fillIn($package, 'Order placed', ['actual_date' => '2027-03-01']);
        // Due on the planned 15 March; the old rule expected it on 11 March from the lead time and reminded of that.
        $this->fillIn($package, 'Delivered from supplier', ['planned_date' => '2027-03-15', 'lead_days' => 10]);
        $milestoneId = $this->project->milestones()->where('name', 'Delivered from supplier')->value('id');
        DB::table('sent_reminders')->insert(['user_id' => $this->owner->id, 'package_id' => $package->id, 'project_milestone_id' => $milestoneId, 'kind' => 'coming_up', 'due_date' => '2027-03-11', 'created_at' => now()]);
        // Never reminded of: still news after the release.
        $this->fillIn($package, 'Request for quotation sent', ['planned_date' => '2027-03-12']);

        $this->forgetNotifiedRiskLevels();
        $this->checkDaily();

        $comingUp = Notification::query()->where('type', 'date_coming_up')->where('notifiable_id', $this->owner->id)->get();
        $this->assertSame(['Request for quotation sent'], $comingUp->pluck('data.params.milestone')->all());
    }

    private function forgetNotifiedRiskLevels(): void
    {
        $migration = require database_path('migrations/2026_11_15_100000_forget_notified_risk_levels.php');
        $migration->up();
    }

    private function addPackage(string $notifiedLevel): Package
    {
        $package = Package::factory()->for($this->project)->create([
            'owner_id' => $this->owner->id,
            'buyer_id' => $this->project->members()->where('role', ProjectRole::Buyer)->value('user_id'),
            'status' => PackageStatus::Ordered,
        ]);
        $package->forceFill(['notified_risk_level' => $notifiedLevel])->save();
        $this->fillIn($package, 'Required on site', ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-01']);

        return $package;
    }

    private function makeLate(Package $package): void
    {
        $milestone = $this->project->milestones()->where('name', 'Required on site')->firstOrFail();
        PackageMilestone::query()->whereBelongsTo($package)->whereBelongsTo($milestone)->update(['forecast_date' => '2027-12-20']);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fillIn(Package $package, string $milestoneName, array $values): void
    {
        $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();

        PackageMilestone::factory()->for($package)->for($milestone)->create($values);
    }

    private function checkDaily(): void
    {
        CheckProjectRisks::dispatchSync($this->project->id, sendDateReminders: true);
    }
}
