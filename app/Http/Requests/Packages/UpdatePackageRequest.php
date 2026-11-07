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
            'owner_id' => $this->personRules('owner_id', ProjectRole::owningPackages()),
            'buyer_id' => $this->personRules('buyer_id', ProjectRole::buyingPackages()),
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
     * Only people who may assign packages send an owner or buyer; for others the fields are left out.
     * A newly chosen person must be an active project member with a suitable role. Keeping the
     * current person is always allowed, so a package whose owner was deactivated can still be saved.
     *
     * @param  list<ProjectRole>  $roles
     * @return list<mixed>
     */
    private function personRules(string $field, array $roles): array
    {
        if (! $this->user()->can('create', [Package::class, $this->project()])) {
            return ['exclude'];
        }

        $isUnchanged = $this->input($field) !== null && (int) $this->input($field) === $this->package()->getAttribute($field);

        return ['nullable', 'integer', Rule::when(! $isUnchanged, [$this->activeMemberWithRole($roles)])];
    }

    /**
     * @param  list<ProjectRole>  $roles
     */
    private function activeMemberWithRole(array $roles): Exists
    {
        return Rule::exists('project_members', 'user_id')
            ->where('project_id', $this->project()->id)
            ->whereIn('role', array_map(fn (ProjectRole $role) => $role->value, $roles))
            ->where(fn ($membership) => $membership->whereExists(fn ($user) => $user
                ->selectRaw('1')
                ->from('users')
                ->whereColumn('users.id', 'project_members.user_id')
                ->whereNull('users.deactivated_at')));
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
