<?php

namespace Tests\Feature\Projects;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Support\Header;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectOverviewAttentionTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday; its week starts on Monday 8 March.
        $this->travelTo('2027-03-10 12:00:00');
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->create(['number_includes_category' => false, 'risk_amber_days' => 7, 'risk_red_days' => 0]);
    }

    /**
     * @return array<string, array{ProjectRole}>
     */
    public static function roles(): array
    {
        return array_combine(
            array_map(fn (ProjectRole $role) => $role->value, ProjectRole::cases()),
            array_map(fn (ProjectRole $role) => [$role], ProjectRole::cases()),
        );
    }

    #[DataProvider('roles')]
    public function test_every_member_sees_the_numbers_after_the_page_opens(ProjectRole $role)
    {
        $user = User::factory()->create();
        ProjectMember::factory()->for($this->project)->for($user)->create(['role' => $role]);
        $this->addPackage('Overdue', dates: ['Order placed' => ['planned_date' => '2027-03-01']]);

        $this->actingAs($user)
            ->get(route('projects.show', $this->project))
            ->assertInertia(fn ($page) => $page
                ->component('projects/Show')
                ->missing('attention')
                ->loadDeferredProps('default', fn ($loaded) => $loaded
                    ->where('attention.tiles.total', 1)
                    ->where('attention.tiles.red', 1)
                    ->where('attention.tiles.overdue', 1)
                    ->where('attention.dueSoonDays', 14)));
    }

    public function test_an_admin_outside_the_project_sees_the_numbers_too()
    {
        $this->addPackage('Fine');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.show', $this->project))
            ->assertInertia(fn ($page) => $page
                ->loadDeferredProps(fn ($loaded) => $loaded->where('attention.tiles.total', 1)));
    }

    public function test_people_outside_the_project_get_nothing_even_when_asking_for_the_numbers_only()
    {
        $this->addPackage('Secret');

        $this->actingAs(User::factory()->create())
            ->withHeaders($this->onlyTheNumbers())
            ->get(route('projects.show', $this->project))
            ->assertForbidden();
    }

    public function test_the_numbers_have_status_labels_and_category_names_from_this_project_only()
    {
        $pipes = ProjectCategory::factory()->for($this->project)->create(['code' => 'PIP', 'name' => 'Piping']);
        $elsewhere = Project::factory()->withDefaultMilestones()->create();
        ProjectCategory::factory()->for($elsewhere)->create(['code' => 'OTH', 'name' => 'Other project']);
        $this->addPackage('Pipe rack', category: $pipes, dates: ['Order placed' => ['planned_date' => '2027-03-01']]);
        $this->addPackage('Loose item', owner: false);
        $this->addPackage('Closed one', status: PackageStatus::Closed, category: $pipes);
        app(CreatePackage::class)->handle($elsewhere, 'Another project’s package', null, null);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.show', $this->project))
            ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($loaded) => $loaded
                ->where('attention.tiles.total', 3)
                ->where('attention.byStatus', fn ($statuses) => $statuses->pluck('value')->all() === array_column(PackageStatus::cases(), 'value')
                    && $statuses->firstWhere('value', 'ordered')['count'] === 2
                    && $statuses->firstWhere('value', 'ordered')['label'] === 'Ordered'
                    && $statuses->firstWhere('value', 'closed')['count'] === 1)
                ->where('attention.riskByCategory', fn ($categories) => $categories->all() === [
                    ['categoryId' => $pipes->id, 'code' => 'PIP', 'name' => 'Piping', 'red' => 1, 'amber' => 0, 'none' => 0],
                    ['categoryId' => null, 'code' => null, 'name' => 'No category', 'red' => 0, 'amber' => 1, 'none' => 0],
                ])
                ->has('attention.milestonesDuePerWeek', 12)
                ->where('attention.milestonesDuePerWeek.0.weekStart', '2027-03-08')));
    }

    public function test_the_numbers_take_the_same_number_of_queries_for_any_number_of_packages()
    {
        $admin = User::factory()->admin()->create();
        $category = ProjectCategory::factory()->for($this->project)->create();
        $this->addPackage('First', category: $category, dates: ['Order placed' => ['planned_date' => '2027-03-20']]);

        $queriesForOne = $this->countQueries(fn () => $this->loadAttention($admin));

        foreach (range(1, 10) as $number) {
            $this->addPackage("Package {$number}", category: $number % 2 === 0 ? $category : null, dates: [
                'Order placed' => ['planned_date' => '2027-03-20'],
                'Delivered from supplier' => ['forecast_date' => '2027-04-20'],
            ]);
        }

        $queriesForEleven = $this->countQueries(fn () => $this->loadAttention($admin));

        $this->assertSame($queriesForOne, $queriesForEleven);
    }

    private function loadAttention(User $user): void
    {
        $this->actingAs($user)
            ->withHeaders($this->onlyTheNumbers())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('attention.tiles')->missing('team'));
    }

    /**
     * The headers of the page's second request, the one for the deferred numbers.
     *
     * @return array<string, string>
     */
    private function onlyTheNumbers(): array
    {
        return [
            Header::VERSION => (string) Inertia::getVersion(),
            Header::PARTIAL_COMPONENT => 'projects/Show',
            Header::PARTIAL_ONLY => 'attention',
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $dates  per milestone name
     */
    private function addPackage(
        string $title,
        bool $owner = true,
        PackageStatus $status = PackageStatus::Ordered,
        ?ProjectCategory $category = null,
        array $dates = [],
    ): Package {
        $package = app(CreatePackage::class)->handle($this->project->fresh(), $title, $category, null);
        $person = User::factory()->create();
        $package->forceFill(['owner_id' => $owner ? $person->id : null, 'buyer_id' => $person->id, 'status' => $status])->save();

        foreach ($dates as $milestoneName => $values) {
            $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();
            PackageMilestone::factory()->for($package)->for($milestone)->create($values);
        }

        return $package;
    }

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
