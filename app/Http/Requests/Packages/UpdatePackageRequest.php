<?php

namespace App\Http\Requests\Packages;

use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Enums\PurchaseType;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdatePackageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->project();

        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => [
                Rule::requiredIf($project->number_includes_category && $this->package()->parent_id === null),
                'nullable',
                'integer',
                Rule::exists('project_categories', 'id')->where('project_id', $project->id),
            ],
            'status' => ['required', Rule::enum(PackageStatus::class)],
            'purchase_type' => ['nullable', Rule::enum(PurchaseType::class)],
            'owner_id' => ['nullable', 'integer', $this->memberWithRole(ProjectRole::owningPackages())],
            'buyer_id' => ['nullable', 'integer', $this->memberWithRole(ProjectRole::buyingPackages())],
            'quantity' => ['nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => __('Choose a category. It is part of the package number.'),
            'owner_id.exists' => __('Choose a project member who may own packages.'),
            'buyer_id.exists' => __('Choose a project member who is a buyer or project manager.'),
        ];
    }

    /**
     * @param  list<ProjectRole>  $roles
     */
    private function memberWithRole(array $roles): Exists
    {
        return Rule::exists('project_members', 'user_id')
            ->where('project_id', $this->project()->id)
            ->whereIn('role', array_map(fn (ProjectRole $role) => $role->value, $roles));
    }

    private function project(): Project
    {
        /** @var Project */
        return $this->route('project');
    }

    private function package(): Package
    {
        /** @var Package */
        return $this->route('package');
    }
}
