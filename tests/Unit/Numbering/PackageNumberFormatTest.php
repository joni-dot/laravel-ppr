<?php

namespace Tests\Unit\Numbering;

use App\Enums\NumberSeparator;
use App\Models\Project;
use PHPUnit\Framework\TestCase;

class PackageNumberFormatTest extends TestCase
{
    public function test_the_suggested_scheme_joins_project_category_and_padded_number()
    {
        $format = $this->project()->numberFormat();

        $this->assertSame('P100-ELE-0007', $format->format(7, 'ELE'));
        $this->assertSame('P100-ELE-0007.01', $format->format(7, 'ELE', 1));
    }

    public function test_left_out_parts_leave_no_empty_separators()
    {
        $format = $this->project([
            'number_includes_project_code' => false,
            'number_includes_category' => false,
        ])->numberFormat();

        $this->assertSame('0007', $format->format(7, 'ELE'));
    }

    public function test_a_prefix_comes_first_and_separators_can_be_left_out()
    {
        $format = $this->project([
            'number_prefix' => 'PKG',
            'number_separator' => NumberSeparator::None,
            'sub_number_separator' => NumberSeparator::Slash,
            'sub_number_padding' => 3,
        ])->numberFormat();

        $this->assertSame('PKGP100ELE0007/012', $format->format(7, 'ELE', 12));
    }

    public function test_a_number_longer_than_its_digits_is_kept_whole()
    {
        $format = $this->project(['number_padding' => 2])->numberFormat();

        $this->assertSame('P100-ELE-12345', $format->format(12345, 'ELE'));
    }

    public function test_a_missing_category_is_left_out()
    {
        $this->assertSame('P100-0007', $this->project()->numberFormat()->format(7));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function project(array $settings = []): Project
    {
        return (new Project)->forceFill([
            'code' => 'P100',
            'number_prefix' => null,
            'number_includes_project_code' => true,
            'number_includes_category' => true,
            'number_separator' => NumberSeparator::Hyphen,
            'number_padding' => 4,
            'sub_number_separator' => NumberSeparator::Dot,
            'sub_number_padding' => 2,
            ...$settings,
        ]);
    }
}
