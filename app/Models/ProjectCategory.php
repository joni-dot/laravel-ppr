<?php

namespace App\Models;

use Database\Factories\ProjectCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A group of packages in one project, such as "ELE Electrical". Its code can be part of package numbers.
 *
 * @property int $id
 * @property int $project_id
 * @property string $code
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectCategory extends Model
{
    /** @use HasFactory<ProjectCategoryFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'category_id');
    }
}
