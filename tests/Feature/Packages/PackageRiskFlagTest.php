<?php

namespace Tests\Feature\Packages;

use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PackageRiskFlagTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->project = Project::factory()->create();
        $this->package = Package::factory()->for($this->project)->create();
    }

    /**
     * @return array<string, array{ProjectRole}>
     */
    public static function rolesEditingEveryPackage(): array
    {
        return [
            'project manager' => [ProjectRole::ProjectManager],
            'buyer' => [ProjectRole::Buyer],
        ];
    }

    #[DataProvider('rolesEditingEveryPackage')]
    public function test_people_who_edit_every_package_flag_and_clear_it(ProjectRole $role)
    {
        $user = $this->member($role);

        $this->actingAs($user)
            ->post($this->url('store'), ['reason' => '  Supplier went bankrupt  '])
            ->assertRedirect(route('projects.packages.show', [$this->project, $this->package]))
            ->assertInertiaFlash('toast.message', 'Package flagged as high risk.');

        $this->package->refresh();
        $this->assertSame('Supplier went bankrupt', $this->package->risk_flag_reason);
        $this->assertSame($user->id, $this->package->risk_flagged_by_id);
        $this->assertSame('2027-03-10 12:00:00', $this->package->risk_flagged_at?->toDateTimeString());

        $this->actingAs($user)
            ->delete($this->url('destroy'))
            ->assertRedirect(route('projects.packages.show', [$this->project, $this->package]));

        $this->assertDatabaseHas('packages', ['id' => $this->package->id, 'risk_flag_reason' => null, 'risk_flagged_by_id' => null, 'risk_flagged_at' => null]);
    }

    public function test_changing_the_reason_records_who_changed_it_and_when()
    {
        $first = $this->member(ProjectRole::ProjectManager);
        $second = $this->member(ProjectRole::Buyer);
        $this->actingAs($first)->post($this->url('store'), ['reason' => 'Supplier went bankrupt']);
        $this->travel(2)->days();

        $this->actingAs($second)->post($this->url('store'), ['reason' => 'New supplier, late start'])->assertRedirect();

        $this->package->refresh();
        $this->assertSame('New supplier, late start', $this->package->risk_flag_reason);
        $this->assertSame($second->id, $this->package->risk_flagged_by_id);
        $this->assertSame('2027-03-12', $this->package->risk_flagged_at?->toDateString());
    }

    public function test_admins_flag_packages_in_any_project()
    {
        $this->actingAs(User::factory()->admin()->create())->post($this->url('store'), ['reason' => 'Checked by audit'])->assertRedirect();

        $this->assertSame('Checked by audit', $this->package->fresh()?->risk_flag_reason);
    }

    public function test_package_owners_flag_only_their_own_packages()
    {
        $owner = $this->member(ProjectRole::PackageOwner);
        $someoneElses = $this->package;
        $own = Package::factory()->for($this->project)->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('projects.packages.risk-flag.store', [$this->project, $own]), ['reason' => 'Mine'])->assertRedirect();
        $this->actingAs($owner)->post($this->url('store'), ['reason' => 'Not mine'])->assertForbidden();
        $this->actingAs($owner)->delete($this->url('destroy'))->assertForbidden();

        $this->assertSame('Mine', $own->fresh()?->risk_flag_reason);
        $this->assertNull($someoneElses->fresh()?->risk_flag_reason);
    }

    public function test_viewers_cannot_flag_or_clear()
    {
        $viewer = $this->member(ProjectRole::Viewer);
        $this->package->forceFill(['risk_flag_reason' => 'Kept', 'risk_flagged_at' => now()])->save();

        $this->actingAs($viewer)->post($this->url('store'), ['reason' => 'Sneaky'])->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('destroy'))->assertForbidden();

        $this->assertSame('Kept', $this->package->fresh()?->risk_flag_reason);
    }

    public function test_people_outside_the_project_get_nothing()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post($this->url('store'), ['reason' => 'Sneaky'])->assertForbidden();
        $this->actingAs($outsider)->delete($this->url('destroy'))->assertForbidden();

        $this->assertNull($this->package->fresh()?->risk_flag_reason);
    }

    public function test_guests_are_sent_to_login()
    {
        $this->post($this->url('store'), ['reason' => 'Sneaky'])->assertRedirect(route('login'));

        $this->assertNull($this->package->fresh()?->risk_flag_reason);
    }

    public function test_a_package_of_another_project_cannot_be_reached_through_this_project()
    {
        $manager = $this->member(ProjectRole::ProjectManager);
        $foreign = Package::factory()->create();

        $this->actingAs($manager)
            ->post(route('projects.packages.risk-flag.store', [$this->project, $foreign]), ['reason' => 'Sneaky'])
            ->assertNotFound();
        $this->actingAs($manager)
            ->delete(route('projects.packages.risk-flag.destroy', [$this->project, $foreign]))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()?->risk_flag_reason);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [null, 'Write why the package is at risk.'],
            'only spaces' => ['   ', 'Write why the package is at risk.'],
            'not text' => [['a', 'b'], 'Write why the package is at risk.'],
            'too long' => [str_repeat('a', 501), 'Keep the reason to 500 characters or fewer.'],
        ];
    }

    #[DataProvider('invalidReasons')]
    public function test_a_reason_is_required_and_has_a_limit(mixed $reason, string $message)
    {
        $this->actingAs($this->member(ProjectRole::ProjectManager))
            ->post($this->url('store'), ['reason' => $reason])
            ->assertSessionHasErrors(['reason' => $message]);

        $this->assertNull($this->package->fresh()?->risk_flag_reason);
    }

    public function test_the_longest_reason_allowed_is_saved()
    {
        $this->actingAs($this->member(ProjectRole::ProjectManager))
            ->post($this->url('store'), ['reason' => str_repeat('a', 500)])
            ->assertSessionHasNoErrors();

        $this->assertSame(500, strlen((string) $this->package->fresh()?->risk_flag_reason));
    }

    public function test_the_database_refuses_a_reason_without_a_flag_time()
    {
        $this->expectException(QueryException::class);

        $this->package->forceFill(['risk_flag_reason' => 'No time'])->save();
    }

    public function test_the_database_refuses_a_flag_time_without_a_reason()
    {
        $this->expectException(QueryException::class);

        $this->package->forceFill(['risk_flagged_at' => now()])->save();
    }

    private function member(ProjectRole $role): User
    {
        $user = User::factory()->create();
        $this->project->members()->make(['role' => $role])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function url(string $action): string
    {
        return route("projects.packages.risk-flag.{$action}", [$this->project, $this->package]);
    }
}
