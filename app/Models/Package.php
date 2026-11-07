<?php

namespace App\Models;

use App\Casts\IntegerArray;
use App\Enums\PackageStatus;
use App\Enums\PurchaseType;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Something a project buys, such as "Main transformer". A package can be divided into
 * sub-packages, which can be divided again without limit.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $parent_id
 * @property string $number
 * @property list<int> $number_path
 * @property int $last_sub_number
 * @property int|null $category_id
 * @property string $title
 * @property string|null $description
 * @property int|null $owner_id
 * @property int|null $buyer_id
 * @property PackageStatus $status
 * @property PurchaseType|null $purchase_type
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $notes
 * @property string|null $risk_flag_reason
 * @property int|null $risk_flagged_by_id
 * @property Carbon|null $risk_flagged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /**
     * The number, its place in the tree, project and parent are set only by CreatePackage.
     */
    protected $fillable = ['category_id', 'title', 'description', 'status', 'purchase_type', 'quantity', 'unit', 'notes'];

    protected $attributes = [
        'status' => 'draft',
        'last_sub_number' => 0,
    ];

    protected function casts(): array
    {
        return [
            'number_path' => IntegerArray::class,
            'status' => PackageStatus::class,
            'purchase_type' => PurchaseType::class,
            'quantity' => 'decimal:3',
            'risk_flagged_at' => 'datetime',
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
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<ProjectCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who last marked the package as high risk by hand.
     *
     * @return BelongsTo<User, $this>
     */
    public function riskFlaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'risk_flagged_by_id');
    }

    /**
     * This package's dates and lead times, one row per project milestone that has any.
     *
     * @return HasMany<PackageMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(PackageMilestone::class);
    }

    /**
     * How many levels below a top-level package this one is: 0 for a top-level package.
     */
    public function depth(): int
    {
        return count($this->number_path) - 1;
    }

    /**
     * The number paths of every package above this one, from the top-level package down.
     *
     * @return list<list<int>>
     */
    public function ancestorNumberPaths(): array
    {
        $paths = [];

        for ($length = 1; $length < count($this->number_path); $length++) {
            $paths[] = array_slice($this->number_path, 0, $length);
        }

        return $paths;
    }

    /**
     * Limits a query on the packages table to this package and every package below it at any depth.
     * Number paths sort like the tree, so everything under [3, 2] lies between [3, 2] and [3, 3],
     * and the project's number path index finds it.
     */
    public function limitToItsTree(QueryBuilder $query): QueryBuilder
    {
        $path = $this->number_path;
        $nextSiblingPath = [...array_slice($path, 0, -1), $path[count($path) - 1] + 1];

        return $query
            ->where('packages.project_id', $this->project_id)
            ->whereRaw('packages.number_path >= ?::integer[] and packages.number_path < ?::integer[]', [
                '{'.implode(',', $path).'}',
                '{'.implode(',', $nextSiblingPath).'}',
            ]);
    }
}
