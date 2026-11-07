<?php

namespace Tests\Feature\Packages;

use App\Actions\Packages\CreatePackage;
use App\Enums\NumberSeparator;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PackageNumberingTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectCategory $electrical;

    private ProjectCategory $mechanical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['code' => 'P100']);
        $this->electrical = ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE']);
        $this->mechanical = ProjectCategory::factory()->for($this->project)->create(['code' => 'MEC']);
    }

    public function test_the_running_number_counts_across_the_whole_project()
    {
        $this->assertSame('P100-ELE-0001', $this->add('Transformer', $this->electrical)->number);
        $this->assertSame('P100-MEC-0002', $this->add('Pump', $this->mechanical)->number);
        $this->assertSame('P100-ELE-0003', $this->add('Cables', $this->electrical)->number);
    }

    public function test_sub_packages_can_be_divided_without_limit()
    {
        $transformer = $this->add('Transformer', $this->electrical);
        $first = $this->add('Core', $this->electrical, $transformer);
        $second = $this->add('Tank', $this->electrical, $transformer);
        $deep = $this->add('Bolts', $this->electrical, $first);
        $deeper = $this->add('Washers', $this->electrical, $deep);

        $this->assertSame('P100-ELE-0001.01', $first->number);
        $this->assertSame('P100-ELE-0001.02', $second->number);
        $this->assertSame('P100-ELE-0001.01.01', $deep->number);
        $this->assertSame('P100-ELE-0001.01.01.01', $deeper->number);
        $this->assertSame([1, 1, 1, 1], $deeper->fresh()->number_path);
        $this->assertSame(3, $deeper->depth());
    }

    public function test_a_sub_package_number_follows_its_parent_even_with_another_category()
    {
        $transformer = $this->add('Transformer', $this->electrical);

        $this->assertSame('P100-ELE-0001.01', $this->add('Foundation', $this->mechanical, $transformer)->number);
    }

    public function test_sub_packages_do_not_use_up_running_numbers()
    {
        $transformer = $this->add('Transformer', $this->electrical);
        $this->add('Core', $this->electrical, $transformer);

        $this->assertSame('P100-MEC-0002', $this->add('Pump', $this->mechanical)->number);
    }

    public function test_numbers_already_given_are_not_given_again_when_the_first_number_is_lowered()
    {
        $this->project->number_start = 50;
        $this->project->save();
        $this->add('Transformer', $this->electrical);

        $this->project->number_start = 1;
        $this->project->save();

        $this->assertSame('P100-ELE-0051', $this->add('Cables', $this->electrical)->number);
    }

    public function test_raising_the_first_number_skips_ahead()
    {
        $this->add('Transformer', $this->electrical);

        $this->project->number_start = 100;
        $this->project->save();

        $this->assertSame('P100-ELE-0100', $this->add('Cables', $this->electrical)->number);
    }

    public function test_changed_numbering_applies_only_to_new_packages()
    {
        $transformer = $this->add('Transformer', $this->electrical);

        $this->project->number_prefix = 'PKG';
        $this->project->sub_number_separator = NumberSeparator::Hyphen;
        $this->project->save();
        $this->electrical->update(['code' => 'EL']);

        $this->assertSame('P100-ELE-0001', $transformer->fresh()->number);
        $this->assertSame('PKG-P100-EL-0002', $this->add('Cables', $this->electrical->fresh())->number);
        $this->assertSame('P100-ELE-0001-01', $this->add('Core', null, $transformer)->number);
    }

    public function test_a_number_that_looks_like_an_older_one_is_skipped()
    {
        // With no separators, prefix "A1" and number 23 look exactly like prefix "A12" and number 3.
        $this->project->forceFill([
            'number_prefix' => 'A1',
            'number_includes_project_code' => false,
            'number_includes_category' => false,
            'number_separator' => NumberSeparator::None,
            'number_padding' => 1,
            'number_start' => 23,
        ])->save();
        $this->assertSame('A123', $this->add('First', null)->number);

        $this->project->forceFill(['number_prefix' => 'A12', 'number_start' => 1, 'last_package_number' => 2])->save();

        $this->assertSame('A124', $this->add('Second', null)->number);
    }

    public function test_numbers_are_separate_in_each_project()
    {
        $this->add('Transformer', $this->electrical);
        $other = Project::factory()->create(['code' => 'P200']);

        $package = app(CreatePackage::class)->handle($other, 'Crane', null);

        $this->assertSame('P200-0001', $package->number);
    }

    public function test_a_parent_from_another_project_is_refused()
    {
        $foreignParent = Package::factory()->create();

        $this->expectException(LogicException::class);

        $this->add('Core', null, $foreignParent);
    }

    public function test_the_database_refuses_two_packages_with_the_same_place_in_the_tree()
    {
        Package::factory()->for($this->project)->create(['number_path' => [1]]);

        $this->expectException(UniqueConstraintViolationException::class);

        Package::factory()->for($this->project)->create(['number_path' => [1]]);
    }

    private function add(string $title, ?ProjectCategory $category, ?Package $parent = null): Package
    {
        return app(CreatePackage::class)->handle($this->project->fresh(), $title, $category, $parent);
    }
}
