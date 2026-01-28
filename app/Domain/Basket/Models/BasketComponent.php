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
class BasketComponent extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'basket_asset_id',
        'asset_code',
        'weight',
        'min_weight',
        'max_weight',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weight'     => 'float',
        'min_weight' => 'float',
        'max_weight' => 'float',
        'is_active'  => 'boolean',
    ];

    /**
     * Get the basket that owns this component.
     * @return BelongsTo<BasketAsset, $this>
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(BasketAsset::class, 'basket_asset_id');
    }

    /**
     * Get the asset for this component.
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }

    /**
     * Check if the component weight is within allowed bounds.
     */
    public function isWithinBounds(float $currentWeight): bool
    {
        if ($this->min_weight !== null && $currentWeight < $this->min_weight) {
            return false;
        }

        if ($this->max_weight !== null && $currentWeight > $this->max_weight) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the value contribution of this component in USD.
     */
    public function calculateValueContribution(): float
    {
        $asset = $this->asset;
        if (! $asset) {
            return 0.0;
        }

        // Get exchange rate to USD
        $rate = 1.0;
        if ($this->asset_code !== 'USD') {
            $exchangeRate = app(\App\Domain\Asset\Services\ExchangeRateService::class)
                ->getRate($this->asset_code, 'USD');

            if ($exchangeRate) {
                $rate = $exchangeRate->rate;
            }
        }

        return $rate * ($this->weight / 100);
    }

    /**
     * Scope a query to only include active components.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Validate the component configuration.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->weight < 0 || $this->weight > 100) {
            $errors[] = 'Weight must be between 0 and 100';
        }

        if ($this->min_weight !== null) {
            if ($this->min_weight < 0 || $this->min_weight > 100) {
                $errors[] = 'Minimum weight must be between 0 and 100';
            }
            if ($this->min_weight > $this->weight) {
                $errors[] = 'Minimum weight cannot be greater than weight';
            }
        }

        if ($this->max_weight !== null) {
            if ($this->max_weight < 0 || $this->max_weight > 100) {
                $errors[] = 'Maximum weight must be between 0 and 100';
            }
            if ($this->max_weight < $this->weight) {
                $errors[] = 'Maximum weight cannot be less than weight';
            }
        }

        if ($this->min_weight !== null && $this->max_weight !== null) {
            if ($this->min_weight > $this->max_weight) {
                $errors[] = 'Minimum weight cannot be greater than maximum weight';
            }
        }

        return $errors;
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
