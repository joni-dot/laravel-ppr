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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DateReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $buyer;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->owner = User::factory()->create(['notification_days' => 7]);
        $this->buyer = User::factory()->create(['notification_days' => 2]);
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember(User::factory()->create(), ProjectRole::ProjectManager)
            ->withMember($this->owner, ProjectRole::PackageOwner)
            ->withMember($this->buyer, ProjectRole::Buyer)
            ->create();
        $this->package = Package::factory()->for($this->project)->create([
            'owner_id' => $this->owner->id,
            'buyer_id' => $this->buyer->id,
            'status' => PackageStatus::Ordered,
        ]);
    }

    public function test_people_are_reminded_of_a_date_within_their_own_period()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-13']);

        $this->checkDaily();

        // The buyer wants to hear only two days ahead.
        $reminder = $this->reminders('date_coming_up')->sole();
        $this->assertSame($this->owner->id, $reminder->notifiable_id);
        $this->assertEquals(['milestone' => 'Order placed', 'date' => '2027-03-13', 'days_left' => 3], $reminder->data['params']);

        $this->travelTo('2027-03-11 12:00:00');
        $this->checkDaily();

        $this->assertSame([$this->owner->id, $this->buyer->id], $this->reminders('date_coming_up')->pluck('notifiable_id')->all());
    }

    public function test_a_date_today_is_coming_up_and_a_date_after_the_period_is_not()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-10']);
        $this->fillIn('Delivered from supplier', ['planned_date' => '2027-03-18']);

        $this->checkDaily();

        $this->assertSame([['Order placed', 0], ['Order placed', 0]], $this->reminders('date_coming_up')->map(fn ($reminder) => [$reminder->data['params']['milestone'], $reminder->data['params']['days_left']])->all());
    }

    public function test_each_date_is_reminded_only_once()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-13']);

        $this->checkDaily();
        $this->checkDaily();
        $this->travelTo('2027-03-12 12:00:00');
        $this->checkDaily();

        $this->assertSame(['date_coming_up', 'date_coming_up'], Notification::query()->where('type', 'like', 'date_%')->pluck('type')->all());
    }

    public function test_a_moved_date_gets_a_reminder_of_its_own()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-13']);
        $this->checkDaily();

        PackageMilestone::query()->whereBelongsTo($this->package)->update(['forecast_date' => '2027-03-15']);
        $this->checkDaily();

        $this->assertSame(['2027-03-13', '2027-03-15'], $this->reminders('date_coming_up')->pluck('data.params.date')->all());
    }

    public function test_a_period_of_zero_turns_off_coming_dates_but_not_overdue_ones()
    {
        $this->owner->forceFill(['notification_days' => 0])->save();
        $this->buyer->forceFill(['notification_days' => 0])->save();
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-10']);

        $this->checkDaily();
        $this->assertCount(0, $this->reminders('date_coming_up'));

        $this->travelTo('2027-03-11 12:00:00');
        $this->checkDaily();

        $this->assertSame([$this->owner->id, $this->buyer->id], $this->reminders('date_overdue')->pluck('notifiable_id')->all());
    }

    public function test_an_overdue_date_is_reminded_only_once()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-08']);

        $this->checkDaily();
        $this->checkDaily();
        $this->travelTo('2027-03-20 12:00:00');
        $this->checkDaily();

        $overdue = $this->reminders('date_overdue');
        $this->assertSame([$this->owner->id, $this->buyer->id], $overdue->pluck('notifiable_id')->all());
        $this->assertEquals(['milestone' => 'Order placed', 'date' => '2027-03-08'], $overdue->first()->data['params']);
    }

    public function test_done_milestones_and_finished_packages_get_no_reminders()
    {
        $closed = Package::factory()->for($this->project)->create(['owner_id' => $this->owner->id, 'status' => PackageStatus::Closed]);
        $cancelled = Package::factory()->for($this->project)->create(['owner_id' => $this->owner->id, 'status' => PackageStatus::Cancelled]);
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-08', 'actual_date' => '2027-03-09']);
        $this->fillIn('Delivered from supplier', ['planned_date' => '2027-03-11', 'actual_date' => '2027-03-10']);
        $this->fillIn('Order placed', ['planned_date' => '2027-03-08'], $closed);
        $this->fillIn('Order placed', ['planned_date' => '2027-03-12'], $cancelled);

        $this->checkDaily();

        $this->assertCount(0, $this->reminders());
    }

    public function test_only_active_members_are_reminded()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-08']);
        $this->owner->forceFill(['deactivated_at' => now()])->save();
        $this->project->members()->where('user_id', $this->buyer->id)->delete();

        $this->checkDaily();

        $this->assertCount(0, $this->reminders());
    }

    public function test_dates_already_overdue_at_a_packages_first_check_are_not_reminded()
    {
        $this->fillIn('Order placed', ['planned_date' => '2027-03-01']);
        $this->fillIn('Delivered from supplier', ['planned_date' => '2027-03-12']);

        $this->checkDaily();
        $this->checkDaily();

        // The date coming up is still news; the old overdue one only shows on the red badge.
        $this->assertCount(0, $this->reminders('date_overdue'));
        $this->assertCount(2, $this->reminders('date_coming_up'));
        $this->assertSame(2, DB::table('sent_reminders')->where('kind', 'overdue')->count());
    }

    public function test_a_save_sends_no_reminders_but_a_first_check_still_records_old_overdue_dates()
    {
        $this->fillIn('Order placed', ['planned_date' => '2027-03-01']);
        $this->fillIn('Delivered from supplier', ['planned_date' => '2027-03-12']);

        $this->checkAfterSave();
        $this->assertCount(0, $this->reminders());

        $this->checkDaily();

        $this->assertCount(0, $this->reminders('date_overdue'));
        $this->assertCount(2, $this->reminders('date_coming_up'));
    }

    public function test_a_removed_milestone_takes_its_reminders_with_it()
    {
        $this->checkAfterSave();
        $this->fillIn('Order placed', ['planned_date' => '2027-03-08']);
        $this->checkDaily();

        $this->project->milestones()->where('name', 'Order placed')->firstOrFail()->delete();

        $this->assertSame(0, DB::table('sent_reminders')->count());
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fillIn(string $milestoneName, array $values, ?Package $package = null): void
    {
        $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();

        PackageMilestone::factory()->for($package ?? $this->package)->for($milestone)->create($values);
    }

    private function checkDaily(): void
    {
        CheckProjectRisks::dispatchSync($this->project->id, sendDateReminders: true);
    }

    private function checkAfterSave(): void
    {
        CheckProjectRisks::dispatchSync($this->project->id);
    }

    /**
     * @return Collection<int, Notification>
     */
    private function reminders(?string $kind = null): Collection
    {
        return Notification::query()
            ->when($kind, fn ($query) => $query->where('type', $kind), fn ($query) => $query->whereIn('type', ['date_coming_up', 'date_overdue']))
            ->orderBy('created_at')
            ->orderBy('notifiable_id')
            ->get();
    }
}
