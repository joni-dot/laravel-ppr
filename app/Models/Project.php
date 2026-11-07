<?php

namespace App\Models;

use App\Enums\NumberSeparator;
use App\Numbering\PackageNumberFormat;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $risk_amber_days
 * @property int $risk_red_days
 * @property string|null $number_prefix
 * @property bool $number_includes_project_code
 * @property bool $number_includes_category
 * @property NumberSeparator $number_separator
 * @property int $number_padding
 * @property int $number_start
 * @property NumberSeparator $sub_number_separator
 * @property int $sub_number_padding
 * @property int|null $last_package_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    protected function casts(): array
    {
        return [
            'number_includes_project_code' => 'boolean',
            'number_includes_category' => 'boolean',
            'number_separator' => NumberSeparator::class,
            'sub_number_separator' => NumberSeparator::class,
        ];
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return HasMany<ProjectCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(ProjectCategory::class);
    }

    /**
     * Each package found through this relation already knows its project, so checking
     * permissions on it doesn't load the project again.
     *
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class)->chaperone('project');
    }

    /**
     * The running number the next top-level package gets. Numbers already given are never
     * given again, even if the first number was lowered after packages were added.
     * The numbering preview uses the same rule: nextRunningNumber() in resources/js/lib/packageNumber.ts.
     */
    public function nextRunningNumber(): int
    {
        return max($this->number_start, ($this->last_package_number ?? 0) + 1);
    }

    public function numberFormat(): PackageNumberFormat
    {
        return new PackageNumberFormat($this);
    }

    /**
     * Admins see every project; everyone else only the projects they are a member of.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereExists(fn ($membership) => $membership
            ->selectRaw('1')
            ->from('project_members')
            ->whereColumn('project_members.project_id', 'projects.id')
            ->where('project_members.user_id', $user->id));
    }
}
