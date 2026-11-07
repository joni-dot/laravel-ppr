<?php

namespace App\Http\Requests\Projects;

use App\Concerns\ProjectValidationRules;
use App\Concerns\RiskWarningRules;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use ProjectValidationRules, RiskWarningRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            ...$this->projectDetailRules($project->id),
            ...$this->riskWarningRules(),
        ];
    }
}
