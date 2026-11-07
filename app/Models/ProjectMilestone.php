<?php

namespace App\Models;

use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A step every package of a project goes through, such as "Order placed". Each project has its own list.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int $position
 * @property bool $is_need_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use HasFactory;

    protected $fillable = ['name', 'position', 'is_need_date'];

    protected $attributes = [
        'is_need_date' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_need_date' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<PackageMilestone, $this>
     */
    public function packageMilestones(): HasMany
    {
        return $this->hasMany(PackageMilestone::class);
    }
}
