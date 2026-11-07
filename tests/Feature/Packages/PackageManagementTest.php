<?php

namespace Tests\Feature\Packages;

use App\Actions\Packages\CreatePackage;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    private ProjectCategory $electrical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->project = Project::factory()->withMember($this->manager, ProjectRole::ProjectManager)->create(['code' => 'P100']);
        $this->electrical = ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE', 'name' => 'Electrical']);
    }

    public function test_a_package_is_added_with_a_title_and_category()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.packages.store', $this->project), ['title' => 'Main transformer', 'category_id' => $this->electrical->id])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Package P100-ELE-0001 added.');

        $this->assertDatabaseHas('packages', [
            'project_id' => $this->project->id,
            'number' => 'P100-ELE-0001',
            'title' => 'Main transformer',
            'category_id' => $this->electrical->id,
            'status' => 'draft',
            'parent_id' => null,
        ]);
    }

    public function test_a_top_level_package_needs_a_category_when_its_number_includes_one()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.packages.store', $this->project), ['title' => 'Main transformer'])
            ->assertSessionHasErrors(['category_id' => 'Choose a category. It is part of the package number.']);

        $this->project->forceFill(['number_includes_category' => false])->save();

        $this->actingAs($this->manager)
            ->post(route('projects.packages.store', $this->project), ['title' => 'Main transformer'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_sub_package_gets_its_parents_category_unless_another_or_none_is_chosen()
    {
        $parent = $this->addPackage('Transformer');
        $mechanical = ProjectCategory::factory()->for($this->project)->create(['code' => 'MEC']);
        $store = route('projects.packages.store', $this->project);

        $this->actingAs($this->manager)->post($store, ['title' => 'Inherits', 'parent_id' => $parent->id]);
        $this->actingAs($this->manager)->post($store, ['title' => 'Own', 'parent_id' => $parent->id, 'category_id' => $mechanical->id]);
        $this->actingAs($this->manager)->post($store, ['title' => 'None', 'parent_id' => $parent->id, 'category_id' => null]);

        $this->assertDatabaseHas('packages', ['title' => 'Inherits', 'category_id' => $this->electrical->id, 'number' => 'P100-ELE-0001.01']);
        $this->assertDatabaseHas('packages', ['title' => 'Own', 'category_id' => $mechanical->id, 'number' => 'P100-ELE-0001.02']);
        $this->assertDatabaseHas('packages', ['title' => 'None', 'category_id' => null, 'number' => 'P100-ELE-0001.03']);
    }

    public function test_the_list_shows_the_whole_tree_in_order()
    {
        $transformer = $this->addPackage('Transformer');
        $cables = $this->addPackage('Cables');
        $core = $this->addPackage('Core', $transformer);
        $this->addPackage('Bolts', $core);
        $this->addPackage('Drums', $cables);

        $this->actingAs($this->manager)
            ->get(route('projects.packages.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->component('projects/packages/Index')
                ->where('packages', fn ($packages) => $packages->pluck('number')->all() === [
                    'P100-ELE-0001', 'P100-ELE-0001.01', 'P100-ELE-0001.01.01', 'P100-ELE-0002', 'P100-ELE-0002.01',
                ] && $packages->pluck('depth')->all() === [0, 1, 2, 0, 1])
                ->where('packages.1.parentId', $transformer->id)
                ->where('packages.0.categoryCode', 'ELE')
                ->where('packages.0.status', 'draft')
                ->where('packages.0.canEdit', true)
                ->where('can.create', true)
                ->where('categoryRequired', true)
                ->has('categories', 1));
    }

    public function test_sub_numbers_past_their_digits_still_sort_as_numbers()
    {
        $parent = $this->addPackage('Transformer');

        foreach (range(1, 100) as $count) {
            $this->addPackage("Part {$count}", $parent);
        }

        $this->actingAs($this->manager)
            ->get(route('projects.packages.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('packages.99.number', 'P100-ELE-0001.99')
                ->where('packages.100.number', 'P100-ELE-0001.100'));
    }

    public function test_the_list_uses_the_same_number_of_queries_for_any_number_of_packages()
    {
        $owner = $this->member(ProjectRole::PackageOwner);
        $this->addPackage('First');

        $queriesForOne = $this->countQueries(fn () => $this->actingAs($owner->fresh())->get(route('projects.packages.index', $this->project)));

        foreach (range(1, 20) as $count) {
            $package = $this->addPackage("Package {$count}");
            $package->owner_id = $owner->id;
            $package->save();
        }

        $queriesForMany = $this->countQueries(fn () => $this->actingAs($owner->fresh())->get(route('projects.packages.index', $this->project)));

        $this->assertSame($queriesForOne, $queriesForMany);
    }

    public function test_the_details_page_shows_the_packages_above_and_below()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $bolts = $this->addPackage('Bolts', $core);
        $this->addPackage('Washers', $bolts);
        $this->addPackage('Nuts', $bolts);

        $this->actingAs($this->manager)
            ->get(route('projects.packages.show', [$this->project, $bolts]))
            ->assertInertia(fn ($page) => $page
                ->component('projects/packages/Show')
                ->where('package.number', 'P100-ELE-0001.01.01')
                ->where('package.category.code', 'ELE')
                ->where('ancestors', fn ($ancestors) => $ancestors->pluck('title')->all() === ['Transformer', 'Core'])
                ->where('subPackages', fn ($children) => $children->pluck('title')->all() === ['Washers', 'Nuts'])
                ->where('can', ['update' => true, 'create' => true]));
    }

    public function test_a_package_is_saved_with_all_its_details()
    {
        $package = $this->addPackage('Transformer');
        $owner = $this->member(ProjectRole::PackageOwner);
        $buyer = $this->member(ProjectRole::Buyer);

        $this->actingAs($this->manager)
            ->put(route('projects.packages.update', [$this->project, $package]), [
                'title' => 'Main transformer',
                'category_id' => $this->electrical->id,
                'status' => 'tendering',
                'purchase_type' => 'equipment',
                'owner_id' => $owner->id,
                'buyer_id' => $buyer->id,
                'quantity' => '2.5',
                'unit' => 'pcs',
                'description' => '110/20 kV',
                'notes' => 'Long lead time',
            ])
            ->assertRedirect(route('projects.packages.show', [$this->project, $package]));

        $package->refresh();
        $this->assertSame('Main transformer', $package->title);
        $this->assertSame('tendering', $package->status->value);
        $this->assertSame('equipment', $package->purchase_type?->value);
        $this->assertSame($owner->id, $package->owner_id);
        $this->assertSame($buyer->id, $package->buyer_id);
        $this->assertSame('2.500', $package->quantity);
        $this->assertSame('P100-ELE-0001', $package->number);
    }

    public function test_changing_the_category_keeps_the_number()
    {
        $package = $this->addPackage('Transformer');
        $mechanical = ProjectCategory::factory()->for($this->project)->create(['code' => 'MEC']);

        $this->actingAs($this->manager)
            ->put(route('projects.packages.update', [$this->project, $package]), ['title' => 'Transformer', 'status' => 'draft', 'category_id' => $mechanical->id]);

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'category_id' => $mechanical->id, 'number' => 'P100-ELE-0001']);
    }

    public function test_only_suitable_project_members_can_own_or_buy_a_package()
    {
        $package = $this->addPackage('Transformer');
        $viewer = $this->member(ProjectRole::Viewer);
        $packageOwner = $this->member(ProjectRole::PackageOwner);
        $outsider = User::factory()->create();
        $update = route('projects.packages.update', [$this->project, $package]);
        $valid = ['title' => 'Transformer', 'status' => 'draft', 'category_id' => $this->electrical->id];

        $this->actingAs($this->manager)->put($update, [...$valid, 'owner_id' => $viewer->id, 'buyer_id' => $packageOwner->id])
            ->assertSessionHasErrors(['owner_id', 'buyer_id']);
        $this->actingAs($this->manager)->put($update, [...$valid, 'owner_id' => $outsider->id, 'buyer_id' => $outsider->id])
            ->assertSessionHasErrors(['owner_id', 'buyer_id']);

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'owner_id' => null, 'buyer_id' => null]);
    }

    public function test_the_edit_page_lists_only_people_who_may_own_or_buy()
    {
        $package = $this->addPackage('Transformer');
        $this->member(ProjectRole::Viewer, 'Vera Viewer');
        $this->member(ProjectRole::PackageOwner, 'Otto Owner');
        $this->member(ProjectRole::Buyer, 'Bea Buyer');
        $this->manager->update(['name' => 'Max Manager']);

        $this->actingAs($this->manager)
            ->get(route('projects.packages.edit', [$this->project, $package]))
            ->assertInertia(fn ($page) => $page
                ->where('canAssignPeople', true)
                ->where('ownerOptions', fn ($people) => $people->pluck('name')->all() === ['Bea Buyer', 'Max Manager', 'Otto Owner'])
                ->where('buyerOptions', fn ($people) => $people->pluck('name')->all() === ['Bea Buyer', 'Max Manager']));
    }

    public function test_invalid_details_are_refused()
    {
        $package = $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->put(route('projects.packages.update', [$this->project, $package]), [
                'title' => '',
                'status' => 'lost',
                'purchase_type' => 'magic',
                'quantity' => '-1',
                'unit' => str_repeat('x', 21),
            ])
            ->assertSessionHasErrors(['title', 'status', 'purchase_type', 'quantity', 'unit', 'category_id']);
    }

    public function test_a_category_in_use_cannot_be_removed()
    {
        $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->delete(route('projects.categories.destroy', [$this->project, $this->electrical]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('project_categories', ['id' => $this->electrical->id]);
    }

    public function test_removing_a_member_takes_them_off_their_packages()
    {
        $person = $this->member(ProjectRole::Buyer);
        $package = $this->addPackage('Transformer');
        $package->owner_id = $person->id;
        $package->buyer_id = $person->id;
        $package->save();
        $membership = ProjectMember::query()->where('user_id', $person->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $membership]))
            ->assertRedirect();

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'owner_id' => null, 'buyer_id' => null]);
    }

    public function test_the_overview_shows_the_next_free_number()
    {
        $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->get(route('projects.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('exampleNumbers', ['package' => 'P100-ELE-0002', 'subPackage' => 'P100-ELE-0002.01']));
    }

    private function addPackage(string $title, ?Package $parent = null): Package
    {
        return app(CreatePackage::class)->handle($this->project->fresh(), $title, $this->electrical, $parent);
    }

    private function member(ProjectRole $role, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        $membership = $this->project->members()->make(['role' => $role]);
        $membership->user_id = $user->id;
        $membership->save();

        return $user;
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
