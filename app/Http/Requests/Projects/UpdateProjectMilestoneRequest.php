<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renames the milestone, makes it the need date, or both.
 */
class UpdateProjectMilestoneRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        /** @var ProjectMilestone $milestone */
        $milestone = $this->route('milestone');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new UniqueIgnoringCase(ProjectMilestone::class, 'name', $milestone->id, ['project_id' => $project->id])],
            // Only "yes": the need date moves by choosing another milestone, so the project always keeps one.
            'is_need_date' => ['sometimes', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_need_date.accepted' => __('A project always has a need date. Make another milestone the need date instead.'),
        ];
    }
}
