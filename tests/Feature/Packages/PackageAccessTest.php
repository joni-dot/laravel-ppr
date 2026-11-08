<?php

namespace Tests\Feature\Packages;

use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\LoadsPackageTree;
use Tests\TestCase;

class PackageAccessTest extends TestCase
{
    use LoadsPackageTree, RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function allPackageRoutes(): array
    {
        return [
            'list' => ['get', 'projects.packages.index'],
            'add' => ['post', 'projects.packages.store'],
            'details' => ['get', 'projects.packages.show'],
            'edit page' => ['get', 'projects.packages.edit'],
            'save' => ['put', 'projects.packages.update'],
        ];
    }

    #[DataProvider('allPackageRoutes')]
    public function test_people_outside_the_project_get_nothing(string $method, string $route)
    {
        $package = Package::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, $this->url($route, $package), $this->validPackage())
            ->assertForbidden();

        $this->assertDatabaseCount('packages', 1);
    }

    public function test_people_outside_the_project_cannot_load_its_package_tree()
    {
        $package = Package::factory()->create();

        $this->actingAs(User::factory()->create())->getPackageTree($package->project)
            ->assertForbidden()
            ->assertDontSee($package->title);
    }

    public function test_guests_are_sent_to_login()
    {
        $package = Package::factory()->create();

        $this->get(route('projects.packages.index', $package->project_id))->assertRedirect(route('login'));
    }

    public function test_viewers_can_look_but_not_change_anything()
    {
        [$viewer, $package] = $this->memberWithPackage(ProjectRole::Viewer);

        $this->actingAs($viewer)->get($this->url('projects.packages.index', $package))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false));
        $this->actingAs($viewer)->getPackageTree($package->project)->assertOk()
            ->assertInertia(fn ($page) => $page->where('packages.0.canEdit', false));
        $this->actingAs($viewer)->get($this->url('projects.packages.show', $package))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can', ['update' => false, 'create' => false]));

        $this->actingAs($viewer)->post($this->url('projects.packages.store', $package), ['title' => 'Sneaky'])->assertForbidden();
        $this->actingAs($viewer)->get($this->url('projects.packages.edit', $package))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('projects.packages.update', $package), $this->validPackage())->assertForbidden();

        $this->assertDatabaseMissing('packages', ['title' => 'Sneaky']);
        $this->assertDatabaseMissing('packages', ['title' => 'Changed']);
    }

    /**
     * @return array<string, array{ProjectRole}>
     */
    public static function rolesManagingPackages(): array
    {
        return [
            'project manager' => [ProjectRole::ProjectManager],
            'buyer' => [ProjectRole::Buyer],
        ];
    }

    #[DataProvider('rolesManagingPackages')]
    public function test_project_managers_and_buyers_add_and_edit_every_package(ProjectRole $role)
    {
        [$user, $package] = $this->memberWithPackage($role);

        $this->actingAs($user)->post($this->url('projects.packages.store', $package), ['title' => 'Crane'])->assertRedirect();
        $this->actingAs($user)->put($this->url('projects.packages.update', $package), $this->validPackage())->assertRedirect();

        $this->assertDatabaseHas('packages', ['title' => 'Crane']);
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'title' => 'Changed']);
    }

    public function test_admins_add_and_edit_packages_in_any_project()
    {
        $package = Package::factory()->for(Project::factory()->create(['number_includes_category' => false]))->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post($this->url('projects.packages.store', $package), ['title' => 'Crane'])->assertRedirect();
        $this->actingAs($admin)->put($this->url('projects.packages.update', $package), $this->validPackage())->assertRedirect();

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'title' => 'Changed']);
    }

    public function test_package_owners_edit_only_their_own_packages()
    {
        [$owner, $own] = $this->memberWithPackage(ProjectRole::PackageOwner);
        $own->owner_id = $owner->id;
        $own->save();
        $someoneElses = Package::factory()->for($own->project)->create();

        $this->actingAs($owner)->get($this->url('projects.packages.index', $own))
            ->assertInertia(fn ($page) => $page->where('can.create', false));
        $this->actingAs($owner)->getPackageTree($own->project)
            ->assertInertia(fn ($page) => $page
                ->where('packages', fn ($packages) => $packages->firstWhere('id', $own->id)['canEdit'] === true
                    && $packages->firstWhere('id', $someoneElses->id)['canEdit'] === false));

        $this->actingAs($owner)->put($this->url('projects.packages.update', $own), $this->validPackage())->assertRedirect();
        $this->actingAs($owner)->get($this->url('projects.packages.edit', $someoneElses))->assertForbidden();
        $this->actingAs($owner)->put($this->url('projects.packages.update', $someoneElses), $this->validPackage())->assertForbidden();
        $this->actingAs($owner)->post($this->url('projects.packages.store', $own), ['title' => 'Crane'])->assertForbidden();

        $this->assertDatabaseHas('packages', ['id' => $own->id, 'title' => 'Changed']);
        $this->assertDatabaseMissing('packages', ['id' => $someoneElses->id, 'title' => 'Changed']);
    }

    public function test_package_owners_cannot_hand_their_package_to_someone_else()
    {
        [$owner, $package] = $this->memberWithPackage(ProjectRole::PackageOwner);
        $package->owner_id = $owner->id;
        $package->save();
        $buyer = User::factory()->create();
        $package->project->members()->make(['role' => ProjectRole::Buyer])->forceFill(['user_id' => $buyer->id])->save();

        $this->actingAs($owner)->get($this->url('projects.packages.edit', $package))
            ->assertInertia(fn ($page) => $page->where('canAssignPeople', false)->where('ownerOptions', [])->where('buyerOptions', []));

        $this->actingAs($owner)
            ->put($this->url('projects.packages.update', $package), [...$this->validPackage(), 'owner_id' => $buyer->id, 'buyer_id' => $buyer->id])
            ->assertRedirect();

        $this->assertDatabaseHas('packages', ['id' => $package->id, 'owner_id' => $owner->id, 'buyer_id' => null]);
    }

    public function test_a_package_of_another_project_cannot_be_reached_through_this_project()
    {
        [$manager, $package] = $this->memberWithPackage(ProjectRole::ProjectManager);
        $foreign = Package::factory()->create();

        $this->actingAs($manager)
            ->get(route('projects.packages.show', [$package->project_id, $foreign->id]))
            ->assertNotFound();
        $this->actingAs($manager)
            ->put(route('projects.packages.update', [$package->project_id, $foreign->id]), $this->validPackage())
            ->assertNotFound();
    }

    public function test_a_parent_or_category_from_another_project_is_refused()
    {
        [$manager, $package] = $this->memberWithPackage(ProjectRole::ProjectManager);
        $foreign = Package::factory()->create();
        $foreignCategory = ProjectCategory::factory()->for($foreign->project)->create();

        $this->actingAs($manager)
            ->post($this->url('projects.packages.store', $package), ['title' => 'Crane', 'parent_id' => $foreign->id, 'category_id' => $foreignCategory->id])
            ->assertSessionHasErrors(['parent_id', 'category_id']);

        $this->assertDatabaseMissing('packages', ['title' => 'Crane']);
    }

    public function test_a_mistyped_package_address_is_not_found()
    {
        [$manager, $package] = $this->memberWithPackage(ProjectRole::ProjectManager);

        $this->actingAs($manager)->get("/projects/{$package->project_id}/packages/abc")->assertNotFound();
    }

    /**
     * @return array{User, Package}
     */
    private function memberWithPackage(ProjectRole $role): array
    {
        $user = User::factory()->create();
        // Without category codes in the numbers, so packages can be added with just a title.
        $project = Project::factory()->withMember($user, $role)->create(['number_includes_category' => false]);

        return [$user, Package::factory()->for($project)->create()];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPackage(): array
    {
        return ['title' => 'Changed', 'status' => 'planned'];
    }

    private function url(string $route, Package $package): string
    {
        return match ($route) {
            'projects.packages.index', 'projects.packages.store' => route($route, $package->project_id),
            default => route($route, [$package->project_id, $package->id]),
        };
    }
}
