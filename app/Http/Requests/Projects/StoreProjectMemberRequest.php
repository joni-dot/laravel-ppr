<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deactivated_at'),
                Rule::unique('project_members', 'user_id')->where('project_id', $project->id),
            ],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => __('This person is already a member of the project.'),
        ];
    }
}
