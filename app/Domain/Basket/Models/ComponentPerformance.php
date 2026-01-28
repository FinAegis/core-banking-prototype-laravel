<?php

namespace App\Domain\Basket\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder having(string $column, string $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static bool delete()
 * @method static bool update(array $values)
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class ComponentPerformance extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'basket_performance_id',
        'asset_code',
        'start_weight',
        'end_weight',
        'average_weight',
        'contribution_value',
        'contribution_percentage',
        'return_value',
        'return_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_weight'            => 'float',
        'end_weight'              => 'float',
        'average_weight'          => 'float',
        'contribution_value'      => 'float',
        'contribution_percentage' => 'float',
        'return_value'            => 'float',
        'return_percentage'       => 'float',
    ];

    /**
     * Get the basket performance record this belongs to.
     */
    public function basketPerformance(): BelongsTo
    {
        return $this->belongsTo(BasketPerformance::class);
    }

    /**
     * Get the asset associated with this component.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }

    /**
     * Get a formatted contribution percentage.
     */
    public function getFormattedContributionAttribute(): string
    {
        $prefix = $this->contribution_percentage >= 0 ? '+' : '';

        return $prefix . number_format($this->contribution_percentage, 2) . '%';
    }

    /**
     * Get a formatted return percentage.
     */
    public function getFormattedReturnAttribute(): string
    {
        $prefix = $this->return_percentage >= 0 ? '+' : '';

        return $prefix . number_format($this->return_percentage, 2) . '%';
    }

    /**
     * Check if this component had a positive contribution.
     */
    public function hasPositiveContribution(): bool
    {
        return $this->contribution_percentage > 0;
    }

    /**
     * Get the weight change during the period.
     */
    public function getWeightChangeAttribute(): float
    {
        return $this->end_weight - $this->start_weight;
    }

    /**
     * Check if the component weight changed significantly.
     */
    public function hasSignificantWeightChange(float $threshold = 1.0): bool
    {
        return abs($this->weight_change) >= $threshold;
    }

    /**
     * Scope a query to only include positive contributors.
     */
    public function scopePositiveContributors($query)
    {
        return $query->where('contribution_percentage', '>', 0);
    }

    /**
     * Scope a query to only include negative contributors.
     */
    public function scopeNegativeContributors($query)
    {
        return $query->where('contribution_percentage', '<', 0);
    }

    /**
     * Scope a query to order by contribution percentage.
     */
    public function scopeOrderByContribution($query, $direction = 'desc')
    {
        return $query->orderBy('contribution_percentage', $direction);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
