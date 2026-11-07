<?php

namespace Tests\Feature\Projects;

use App\Enums\NumberSeparator;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectNumberingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->project = Project::factory()->withMember($this->manager, ProjectRole::ProjectManager)->create(['code' => 'P100']);
    }

    public function test_new_projects_start_with_the_suggested_numbering()
    {
        $this->actingAs($this->manager)
            ->get(route('projects.numbering.edit', $this->project))
            ->assertInertia(fn ($page) => $page
                ->component('projects/Numbering')
                ->where('numbering', [
                    'prefix' => null,
                    'includesProjectCode' => true,
                    'includesCategory' => true,
                    'separator' => 'hyphen',
                    'padding' => 4,
                    'start' => 1,
                    'subSeparator' => 'dot',
                    'subPadding' => 2,
                ])
                ->has('separators', 5)
                ->has('subSeparators', 4));
    }

    public function test_a_project_manager_can_change_the_numbering()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.numbering.update', $this->project), $this->numbering([
                'number_prefix' => 'PKG',
                'number_includes_category' => false,
                'number_separator' => 'slash',
                'number_padding' => 3,
                'number_start' => 100,
            ]))
            ->assertSessionHasNoErrors();

        $this->project->refresh();
        $this->assertSame('PKG/P100/100', $this->project->numberFormat()->format(100, 'ELE'));
        $this->assertSame(NumberSeparator::Slash, $this->project->number_separator);
    }

    public function test_an_empty_prefix_is_saved_as_no_prefix()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.numbering.update', $this->project), $this->numbering(['number_prefix' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->project->refresh()->number_prefix);
    }

    public function test_sub_package_numbers_always_have_a_visible_separator()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.numbering.update', $this->project), $this->numbering(['sub_number_separator' => 'none']))
            ->assertSessionHasErrors('sub_number_separator');
    }

    public function test_numbering_values_stay_within_limits()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.numbering.update', $this->project), $this->numbering([
                'number_prefix' => 'P-K',
                'number_padding' => 9,
                'number_start' => 0,
                'sub_number_padding' => 5,
                'number_separator' => 'comma',
            ]))
            ->assertSessionHasErrors(['number_prefix', 'number_padding', 'number_start', 'sub_number_padding', 'number_separator']);
    }

    public function test_a_project_manager_can_add_edit_and_remove_categories()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.categories.store', $this->project), ['code' => 'ELE', 'name' => 'Electrical'])
            ->assertSessionHasNoErrors();

        $category = $this->project->categories()->sole();

        $this->actingAs($this->manager)
            ->put(route('projects.categories.update', [$this->project, $category]), ['code' => 'EL', 'name' => 'Electrics'])
            ->assertSessionHasNoErrors();

        $this->assertSame('EL', $category->refresh()->code);

        $this->actingAs($this->manager)
            ->delete(route('projects.categories.destroy', [$this->project, $category]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($category);
    }

    public function test_category_codes_are_unique_within_a_project_whatever_their_case()
    {
        ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE']);
        ProjectCategory::factory()->create(['code' => 'MEC']);

        $this->actingAs($this->manager)
            ->post(route('projects.categories.store', $this->project), ['code' => 'ele', 'name' => 'Copy'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->manager)
            ->post(route('projects.categories.store', $this->project), ['code' => 'MEC', 'name' => 'Mechanical'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_category_keeps_its_own_code_when_saved_unchanged()
    {
        $category = ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE']);

        $this->actingAs($this->manager)
            ->put(route('projects.categories.update', [$this->project, $category]), ['code' => 'ELE', 'name' => 'Renamed'])
            ->assertSessionHasNoErrors();
    }

    public function test_categories_of_another_project_cannot_be_reached_through_this_one()
    {
        $otherCategory = ProjectCategory::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('projects.categories.destroy', [$this->project, $otherCategory]))
            ->assertNotFound();

        $this->assertModelExists($otherCategory);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function numbering(array $changes = []): array
    {
        return [
            'number_prefix' => null,
            'number_includes_project_code' => true,
            'number_includes_category' => true,
            'number_separator' => 'hyphen',
            'number_padding' => 4,
            'number_start' => 1,
            'sub_number_separator' => 'dot',
            'sub_number_padding' => 2,
            ...$changes,
        ];
    }
}
