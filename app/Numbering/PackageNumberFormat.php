<?php

namespace App\Numbering;

use App\Models\Project;

/**
 * Builds package numbers from a project's numbering settings, such as
 * "P100-ELE-0007" and "P100-ELE-0007.01" for its first sub-package.
 *
 * The numbering page shows a live preview made by resources/js/lib/packageNumber.ts,
 * which follows the same rules. A browser test checks that both agree.
 */
final readonly class PackageNumberFormat
{
    /**
     * Shown in examples when the project has no categories yet.
     */
    public const string EXAMPLE_CATEGORY_CODE = 'ABC';

    public function __construct(private Project $project) {}

    public function format(int $runningNumber, ?string $categoryCode = null, ?int $subNumber = null): string
    {
        $number = implode($this->project->number_separator->character(), $this->parts($runningNumber, $categoryCode));

        return $subNumber === null ? $number : $this->subPackageNumber($number, $subNumber);
    }

    /**
     * A sub-package's number is its parent's number plus its own sub-number, at any depth,
     * such as "P100-ELE-0007.01.03" for the third sub-package of "P100-ELE-0007.01".
     */
    public function subPackageNumber(string $parentNumber, int $subNumber): string
    {
        return $parentNumber.$this->project->sub_number_separator->character().$this->pad($subNumber, $this->project->sub_number_padding);
    }

    /**
     * @return list<string>
     */
    private function parts(int $runningNumber, ?string $categoryCode): array
    {
        $parts = [
            $this->project->number_prefix,
            $this->project->number_includes_project_code ? $this->project->code : null,
            $this->project->number_includes_category ? $categoryCode : null,
            $this->pad($runningNumber, $this->project->number_padding),
        ];

        return array_values(array_filter($parts, fn (?string $part) => $part !== null && $part !== ''));
    }

    private function pad(int $number, int $length): string
    {
        return str_pad((string) $number, $length, '0', STR_PAD_LEFT);
    }
}
