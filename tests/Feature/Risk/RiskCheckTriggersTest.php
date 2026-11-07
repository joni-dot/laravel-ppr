<?php

namespace Tests\Feature\Risk;

use App\Enums\ProjectRole;
use App\Jobs\CheckProjectRisks;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every save that can change a package's risk queues a check of its project, carrying who saved,
 * and the daily run checks every project with date reminders.
 */
class RiskCheckTriggersTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->manager = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->create(['number_includes_category' => false, 'risk_amber_days' => 14, 'risk_red_days' => 0]);
        $this->package = Package::factory()->for($this->project)->create();
    }

    public function test_adding_a_package_queues_a_check()
    {
        $this->assertQueuesCheck(fn () => $this->post(route('projects.packages.store', $this->project), ['title' => 'Pump']));
    }

    public function test_saving_a_package_queues_a_check()
    {
        $this->assertQueuesCheck(fn () => $this->put(route('projects.packages.update', [$this->project, $this->package]), ['title' => 'Pump', 'status' => 'ordered']));
    }

    public function test_saving_a_packages_dates_queues_a_check()
    {
        $this->assertQueuesCheck(fn () => $this->put(route('projects.packages.schedule.update', [$this->project, $this->package]), [
            'milestones' => [['id' => $this->milestone('Order placed')->id, 'planned_date' => '2027-01-10', 'forecast_date' => null, 'actual_date' => null, 'lead_days' => null]],
        ]));
    }

    public function test_flagging_and_clearing_the_risk_flag_queue_a_check()
    {
        $this->assertQueuesCheck(fn () => $this->post(route('projects.packages.risk-flag.store', [$this->project, $this->package]), ['reason' => 'Supplier went bankrupt']));
        $this->assertQueuesCheck(fn () => $this->delete(route('projects.packages.risk-flag.destroy', [$this->project, $this->package])));
    }

    public function test_changing_the_risk_warnings_queues_a_check_but_renaming_the_project_does_not()
    {
        $project = ['name' => $this->project->name, 'code' => $this->project->code, 'risk_amber_days' => 14, 'risk_red_days' => 0];

        $this->assertQueuesCheck(fn () => $this->put(route('projects.update', $this->project), [...$project, 'risk_amber_days' => 30]));
        $this->assertQueuesNoCheck(fn () => $this->put(route('projects.update', $this->project), [...$project, 'name' => 'Renamed', 'risk_amber_days' => 30]));
    }

    public function test_changing_the_milestones_queues_a_check()
    {
        $this->assertQueuesCheck(fn () => $this->post(route('projects.milestones.store', $this->project), ['name' => 'Tested']));
        $this->assertQueuesCheck(fn () => $this->patch(route('projects.milestones.move', [$this->project, $this->milestone('Order placed')]), ['direction' => 'up']));
        $this->assertQueuesCheck(fn () => $this->put(route('projects.milestones.update', [$this->project, $this->milestone('Delivered from supplier')]), ['is_need_date' => true]));
        $this->assertQueuesCheck(fn () => $this->delete(route('projects.milestones.destroy', [$this->project, $this->milestone('Offers received')])));
    }

    public function test_renaming_a_milestone_queues_no_check()
    {
        $this->assertQueuesNoCheck(fn () => $this->put(route('projects.milestones.update', [$this->project, $this->milestone('Order placed')]), ['name' => 'Order sent']));
    }

    public function test_changing_a_members_role_or_removing_them_queues_a_check()
    {
        $member = $this->addMember(User::factory()->create(), ProjectRole::Buyer);

        $this->assertQueuesCheck(fn () => $this->patch(route('projects.members.update', [$this->project, $member]), ['role' => 'viewer']));
        $this->assertQueuesCheck(fn () => $this->delete(route('projects.members.destroy', [$this->project, $member])));
    }

    public function test_deactivating_or_reactivating_someone_checks_each_of_their_projects()
    {
        $admin = User::factory()->admin()->create();
        $person = User::factory()->create();
        $otherProject = Project::factory()->withMember($person, ProjectRole::Buyer)->create();
        Project::factory()->create();
        $this->addMember($person, ProjectRole::PackageOwner);

        foreach (['admin.users.deactivate', 'admin.users.reactivate'] as $route) {
            Queue::fake();
            $this->actingAs($admin)->post(route($route, $person))->assertSessionHasNoErrors();

            $this->assertEqualsCanonicalizing(
                [$this->project->id, $otherProject->id],
                Queue::pushed(CheckProjectRisks::class)->map(fn (CheckProjectRisks $job) => $job->projectId)->all(),
            );
            Queue::assertPushed(CheckProjectRisks::class, fn (CheckProjectRisks $job) => $job->changedById === $admin->id);
        }
    }

    public function test_the_daily_command_checks_every_project_with_date_reminders()
    {
        $otherProject = Project::factory()->create();

        $this->artisan('packages:check-risks')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$this->project->id, $otherProject->id],
            Queue::pushed(CheckProjectRisks::class)->map(fn (CheckProjectRisks $job) => $job->projectId)->all(),
        );
        Queue::assertPushed(CheckProjectRisks::class, fn (CheckProjectRisks $job) => $job->sendDateReminders && $job->changedById === null);
    }

    public function test_the_daily_command_runs_early_every_morning_in_finland()
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'packages:check-risks'));

        $this->assertNotNull($event);
        $this->assertSame('0 4 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    /**
     * @param  callable(): TestResponse  $request
     */
    private function assertQueuesCheck(callable $request): void
    {
        Queue::fake();
        $this->actingAs($this->manager);

        $request()->assertSessionHasNoErrors();

        Queue::assertPushed(CheckProjectRisks::class, 1);
        Queue::assertPushed(CheckProjectRisks::class, fn (CheckProjectRisks $job) => $job->projectId === $this->project->id
            && $job->changedById === $this->manager->id
            && ! $job->sendDateReminders);
    }

    /**
     * @param  callable(): TestResponse  $request
     */
    private function assertQueuesNoCheck(callable $request): void
    {
        Queue::fake();
        $this->actingAs($this->manager);

        $request()->assertSessionHasNoErrors();

        Queue::assertNotPushed(CheckProjectRisks::class);
    }

    private function milestone(string $name): ProjectMilestone
    {
        return $this->project->milestones()->where('name', $name)->firstOrFail();
    }

    private function addMember(User $user, ProjectRole $role): ProjectMember
    {
        $member = $this->project->members()->make(['role' => $role]);
        $member->user_id = $user->id;
        $member->save();

        return $member;
    }
}
