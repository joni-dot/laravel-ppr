<?php

namespace App\Models;

use Database\Factories\PackageMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One package's dates and lead time for one of its project's milestones.
 * A row exists only once something has been filled in.
 *
 * @property int $id
 * @property int $package_id
 * @property int $project_milestone_id
 * @property Carbon|null $planned_date
 * @property Carbon|null $forecast_date
 * @property Carbon|null $actual_date
 * @property int|null $lead_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PackageMilestone extends Model
{
    /** @use HasFactory<PackageMilestoneFactory> */
    use HasFactory;

    protected $fillable = ['planned_date', 'forecast_date', 'actual_date', 'lead_days'];

    protected function casts(): array
    {
        return [
            'planned_date' => 'date:Y-m-d',
            'forecast_date' => 'date:Y-m-d',
            'actual_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<ProjectMilestone, $this>
     */
    public function projectMilestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    public function isEmpty(): bool
    {
        return $this->planned_date === null && $this->forecast_date === null
            && $this->actual_date === null && $this->lead_days === null;
    }
}
