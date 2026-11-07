<?php

namespace App\Http\Requests\Packages;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->project();

        return [
            'title' => ['required', 'string', 'max:200'],
            'parent_id' => ['nullable', 'integer', Rule::exists('packages', 'id')->where('project_id', $project->id)],
            'category_id' => [
                // A top-level package's number can include its category code, so it must have one.
                Rule::requiredIf($project->number_includes_category && $this->input('parent_id') === null),
                'nullable',
                'integer',
                Rule::exists('project_categories', 'id')->where('project_id', $project->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => __('Choose a category. It is part of the package number.'),
        ];
    }

    private function project(): Project
    {
        /** @var Project */
        return $this->route('project');
    }
}
