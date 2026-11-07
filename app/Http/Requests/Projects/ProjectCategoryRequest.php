<?php

namespace App\Http\Requests\Projects;

use App\Concerns\ProjectValidationRules;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectCategoryRequest extends FormRequest
{
    use ProjectValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        /** @var ProjectCategory|null $category */
        $category = $this->route('category');

        return [
            'code' => ['required', 'string', 'max:10', self::CODE_PATTERN, new UniqueIgnoringCase(ProjectCategory::class, 'code', $category?->id, ['project_id' => $project->id])],
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
