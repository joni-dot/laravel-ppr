<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectMilestoneRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:100', new UniqueIgnoringCase(ProjectMilestone::class, 'name', null, ['project_id' => $project->id])],
        ];
    }
}
