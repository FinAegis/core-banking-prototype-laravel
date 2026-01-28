<?php

namespace App\Domain\Basket\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Database\Factories\BasketValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereBetween(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder withCount(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', \Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder limit(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder take(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder skip(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder offset(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder lockForUpdate()
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static static forceCreate(array $attributes)
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static bool delete()
 * @method static bool forceDelete()
 * @method static bool restore()
 * @method static bool update(array $attributes = [])
 * @method static int increment(string $column, float|int $amount = 1, array $extra = [])
 * @method static int decrement(string $column, float|int $amount = 1, array $extra = [])
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class BasketValue extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return BasketValueFactory
     */
    protected static function newFactory()
    {
        return BasketValueFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'basket_asset_code',
        'value',
        'calculated_at',
        'component_values',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value'            => 'float',
        'calculated_at'    => 'datetime',
        'component_values' => 'array',
    ];

    /**
     * Get the basket associated with this value.
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(BasketAsset::class, 'basket_asset_code', 'code');
    }

    /**
     * Get the total weight of all components.
     */
    public function getTotalWeight(): float
    {
        if (! $this->component_values) {
            return 0.0;
        }

        return collect($this->component_values)
            ->sum('weight');
    }

    /**
     * Get the value contribution of a specific component.
     */
    public function getComponentValue(string $assetCode): ?float
    {
        if (! $this->component_values || ! isset($this->component_values[$assetCode])) {
            return null;
        }

        return $this->component_values[$assetCode]['weighted_value'] ?? null;
    }

    /**
     * Get the actual weight of a component based on current values.
     */
    public function getActualWeight(string $assetCode): ?float
    {
        $componentValue = $this->getComponentValue($assetCode);
        if ($componentValue === null || $this->value == 0) {
            return null;
        }

        return ($componentValue / $this->value) * 100;
    }

    /**
     * Check if this value calculation is recent.
     */
    public function isFresh(int $minutes = 5): bool
    {
        return $this->calculated_at->diffInMinutes(now()) <= $minutes;
    }

    /**
     * Get the performance compared to a previous value.
     */
    public function getPerformance(self $previousValue): array
    {
        $change = $this->value - $previousValue->value;
        $percentageChange = $previousValue->value > 0
            ? ($change / $previousValue->value) * 100
            : 0;

        return [
            'previous_value'    => $previousValue->value,
            'current_value'     => $this->value,
            'change'            => $change,
            'percentage_change' => $percentageChange,
            'time_period'       => $previousValue->calculated_at->diffForHumans($this->calculated_at),
        ];
    }

    /**
     * Scope a query to get values within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('calculated_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get the latest value for each basket.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('calculated_at', 'desc');
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
