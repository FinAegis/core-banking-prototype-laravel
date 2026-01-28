<?php

namespace App\Domain\Basket\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Database\Factories\BasketAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static mixed sum(string $column)
 * @method static int count(string $columns = '*')
 * @method static static|null first()
 * @method static \Illuminate\Database\Eloquent\Collection get(array|string $columns = ['*'])
 */
class BasketAsset extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return BasketAssetFactory
     */
    protected static function newFactory()
    {
        return BasketAssetFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'rebalance_frequency',
        'last_rebalanced_at',
        'is_active',
        'created_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_rebalanced_at' => 'datetime',
        'is_active'          => 'boolean',
        'metadata'           => 'array',
    ];

    /**
     * Get the components of this basket.
     */
    public function components(): HasMany
    {
        return $this->hasMany(BasketComponent::class);
    }

    /**
     * Get the active components of this basket.
     * @return HasMany<BasketComponent>
     */
    public function activeComponents(): HasMany
    {
        return $this->hasMany(BasketComponent::class)->where('is_active', true);
    }

    /**
     * Get the value history of this basket.
     */
    public function values(): HasMany
    {
        return $this->hasMany(BasketValue::class, 'basket_asset_code', 'code');
    }

    /**
     * Get the performance records of this basket.
     */
    public function performances(): HasMany
    {
        return $this->hasMany(BasketPerformance::class, 'basket_asset_code', 'code');
    }

    /**
     * Get the latest value of this basket.
     */
    /**
     * @return HasOne
     */
    public function latestValue(): HasOne
    {
        return $this->hasOne(BasketValue::class, 'basket_asset_code', 'code')
            ->orderBy('calculated_at', 'desc');
    }

    /**
     * Get the creator of this basket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    /**
     * Check if the basket needs rebalancing.
     */
    public function needsRebalancing(): bool
    {
        if ($this->type !== 'dynamic' || $this->rebalance_frequency === 'never') {
            return false;
        }

        if (! $this->last_rebalanced_at) {
            return true;
        }

        $nextRebalanceDate = match ($this->rebalance_frequency) {
            'daily'     => $this->last_rebalanced_at->addDay(),
            'weekly'    => $this->last_rebalanced_at->addWeek(),
            'monthly'   => $this->last_rebalanced_at->addMonth(),
            'quarterly' => $this->last_rebalanced_at->addQuarter(),
            default     => null,
        };

        return $nextRebalanceDate && now()->gte($nextRebalanceDate);
    }

    /**
     * Validate that component weights sum to 100%.
     */
    public function validateWeights(): bool
    {
        $totalWeight = $this->activeComponents()->sum('weight');

        return abs($totalWeight - 100) < 0.01; // Allow for floating point precision
    }

    /**
     * Get the basket as an Asset for compatibility.
     */
    public function toAsset(): Asset
    {
        return Asset::firstOrCreate(
            ['code' => $this->code],
            [
                'name'      => $this->name,
                'type'      => 'custom',
                'precision' => 4,
                'is_active' => $this->is_active ?? true,
                'is_basket' => true,
                'metadata'  => [
                    'basket_id'     => $this->id,
                    'basket_type'   => $this->type,
                    'asset_subtype' => 'basket',
                ],
            ]
        );
    }

    /**
     * Calculate the total value of the basket in USD.
     */
    public function calculateValue(): float
    {
        $value = 0.0;

        foreach ($this->activeComponents as $component) {
            $asset = Asset::find($component->asset_code);
            if (! $asset) {
                continue;
            }

            // Get exchange rate to USD
            $rate = 1.0;
            if ($component->asset_code !== 'USD') {
                $exchangeRate = app(\App\Domain\Asset\Services\ExchangeRateService::class)
                    ->getRate($component->asset_code, 'USD');

                if ($exchangeRate) {
                    $rate = $exchangeRate->rate;
                }
            }

            // Add weighted value
            $value += $rate * ($component->weight / 100);
        }

        return $value;
    }

    /**
     * Scope a query to only include active baskets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include baskets that need rebalancing.
     */
    public function scopeNeedsRebalancing($query)
    {
        return $query->where('type', 'dynamic')
            ->where('rebalance_frequency', '!=', 'never')
            ->where(
                function ($q) {
                    $q->whereNull('last_rebalanced_at')
                        ->orWhere(
                            function ($q2) {
                                $q2->where('rebalance_frequency', 'daily')
                                    ->where('last_rebalanced_at', '<=', now()->subDay());
                            }
                        )
                    ->orWhere(
                        function ($q2) {
                            $q2->where('rebalance_frequency', 'weekly')
                                ->where('last_rebalanced_at', '<=', now()->subWeek());
                        }
                    )
                    ->orWhere(
                        function ($q2) {
                            $q2->where('rebalance_frequency', 'monthly')
                                ->where('last_rebalanced_at', '<=', now()->subMonth());
                        }
                    )
                    ->orWhere(
                        function ($q2) {
                            $q2->where('rebalance_frequency', 'quarterly')
                                ->where('last_rebalanced_at', '<=', now()->subQuarter());
                        }
                    );
                }
            );
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
