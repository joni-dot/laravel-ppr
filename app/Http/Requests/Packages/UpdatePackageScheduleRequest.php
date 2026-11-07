<?php

namespace App\Http\Requests\Packages;

use App\Models\Package;
use App\Models\Project;
use App\Scheduling\Days;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePackageScheduleRequest extends FormRequest
{
    /**
     * The project's milestone ids in order, read once for the whole request.
     *
     * @var list<int>|null
     */
    private ?array $projectMilestoneIds = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $milestoneIds = $this->projectMilestoneIds();
        $dateRules = ['present', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'];

        return [
            // Milestones left out keep their dates.
            'milestones' => ['present', 'list', 'max:'.count($milestoneIds)],
            'milestones.*' => ['array:id,planned_date,forecast_date,actual_date,lead_days'],
            // Checked against the ids read above, so the rows cost no query each.
            'milestones.*.id' => ['required', 'integer', 'distinct', Rule::in($milestoneIds)],
            'milestones.*.planned_date' => $dateRules,
            'milestones.*.forecast_date' => $dateRules,
            'milestones.*.actual_date' => [...$dateRules, 'before_or_equal:'.Days::latestToday()],
            'milestones.*.lead_days' => ['present', 'nullable', 'integer', 'between:0,3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $dateRange = __('Enter a date between the years 2000 and 2100.');

        return [
            'milestones.max' => __('Each milestone can be saved only once.'),
            'milestones.*.id.distinct' => __('Each milestone can be saved only once.'),
            'milestones.*.id.in' => __('This milestone is not part of the project.'),
            // Before the general date messages, because the first matching message is used.
            'milestones.*.actual_date.before_or_equal' => __('An actual date can’t be in the future.'),
            'milestones.*.*.date_format' => $dateRange,
            'milestones.*.*.after_or_equal' => $dateRange,
            'milestones.*.*.before_or_equal' => $dateRange,
            'milestones.*.lead_days.integer' => __('Enter the lead time as whole days.'),
            'milestones.*.lead_days.between' => __('Enter a lead time between 0 and 3650 days.'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->keepSetPlannedDates($validator)];
    }

    /**
     * The validated rows, ready to save.
     *
     * @return list<array{id: int, planned_date: string|null, forecast_date: string|null, actual_date: string|null, lead_days: int|null}>
     */
    public function milestoneRows(): array
    {
        $firstMilestoneId = $this->projectMilestoneIds()[0] ?? null;

        return array_values(array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'planned_date' => self::dateOrNull($row['planned_date']),
            'forecast_date' => self::dateOrNull($row['forecast_date']),
            'actual_date' => self::dateOrNull($row['actual_date']),
            // Lead time counts from the previous milestone, so the first one has none. Ignored rather than
            // refused: after the project manager reorders the list, a stored value may land on the first row.
            'lead_days' => (int) $row['id'] === $firstMilestoneId || $row['lead_days'] === null ? null : (int) $row['lead_days'],
        ], $this->validated('milestones')));
    }

    /**
     * A validated date field holds a "YYYY-MM-DD" string or nothing.
     */
    private static function dateOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Anyone who may update the package can fill in a missing planned date, but only project managers
     * and admins may move or clear one that is set (see PackagePolicy::changePlannedDates).
     */
    private function keepSetPlannedDates(Validator $validator): void
    {
        $rows = $this->input('milestones');

        if (! is_array($rows) || $this->user()->can('changePlannedDates', [Package::class, $this->project()])) {
            return;
        }

        $setDates = $this->package()->milestones()->whereNotNull('planned_date')->toBase()->pluck('planned_date', 'project_milestone_id');

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! is_scalar($row['id'] ?? null)) {
                continue;
            }

            $setDate = $setDates[(int) $row['id']] ?? null;

            if ($setDate !== null && ($row['planned_date'] ?? null) !== $setDate) {
                $validator->errors()->add("milestones.{$index}.planned_date", __('Only a project manager can change a planned date once it is set.'));
            }
        }
    }

    /**
     * @return list<int>
     */
    private function projectMilestoneIds(): array
    {
        return $this->projectMilestoneIds ??= array_values($this->project()->milestones()->pluck('id')->all());
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
