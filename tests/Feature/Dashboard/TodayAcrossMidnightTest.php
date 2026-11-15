<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Packages\CreatePackage;
use App\Dashboard\ProjectAttention;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use App\Risk\RiskReason;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\LoadsPackageTree;
use Tests\TestCase;

/**
 * A request that runs across midnight UTC must count everything from one day. Otherwise a package whose
 * milestone is expected that day is neither overdue (the risk rules saw it as today) nor due soon (the
 * filter already counts from tomorrow), and a dashboard number disagrees with the list it opens.
 */
class TodayAcrossMidnightTest extends TestCase
{
    use LoadsPackageTree, RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->create(['number_includes_category' => false]);

        $package = app(CreatePackage::class)->handle($this->project->fresh(), 'Order due today', null, null);
        $package->forceFill(['owner_id' => $this->manager->id, 'buyer_id' => $this->manager->id, 'status' => PackageStatus::Planned])->save();
        $milestone = $this->project->milestones()->where('name', 'Order placed')->firstOrFail();
        PackageMilestone::factory()->for($package)->for($milestone)->create(['forecast_date' => '2027-03-10']);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    public function test_the_dashboard_counts_a_package_due_that_day_as_overdue_or_due_soon(int $reads)
    {
        $this->midnightPassesAfter($reads);

        $tiles = ProjectAttention::for($this->project)->tiles();

        $this->assertSame(1, $tiles['overdue'] + $tiles['dueSoon']);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    public function test_the_package_list_puts_a_package_due_that_day_in_the_overdue_or_due_soon_filter(int $reads)
    {
        $this->actingAs($this->manager);
        $this->midnightPassesAfter($reads);

        $props = $this->getPackageTree($this->project)->viewData('page')['props'];
        $row = json_decode(json_encode($props['packages'][0]), true);

        $dueSoon = $row['dueDate'] !== null && $row['dueDate'] >= $props['today'];
        $overdue = in_array(RiskReason::Overdue->value, $row['risk']['reasons'] ?? [], true);
        $this->assertTrue($dueSoon || $overdue, 'The package is in neither filter: today '.$props['today'].', due '.$row['dueDate']);
    }

    /**
     * From here on the clock shows 23:59:59 UTC for the app's own first $reads readings, and the next day
     * after that, as if midnight passed during the request. Trying several points catches a second reading
     * wherever it is.
     */
    private function midnightPassesAfter(int $reads): void
    {
        $readsByTheApp = 0;

        Carbon::setTestNow(function () use (&$readsByTheApp, $reads) {
            $passed = $readsByTheApp >= $reads;
            $readByTheApp = array_any(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                fn (array $frame) => str_starts_with($frame['class'] ?? '', 'App\\'),
            );
            $readsByTheApp += $readByTheApp ? 1 : 0;

            return new DateTimeImmutable($passed ? '2027-03-11 00:00:00' : '2027-03-10 23:59:59', new DateTimeZone('UTC'));
        });
    }
}
