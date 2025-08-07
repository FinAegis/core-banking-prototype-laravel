<?php

namespace App\Domain\Stablecoin\Models;

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property string $peg_asset_code
 * @property float $peg_ratio
 * @property float $target_price
 * @property string $stability_mechanism
 * @property float $collateral_ratio
 * @property float $min_collateral_ratio
 * @property float $minimum_collateralization_ratio
 * @property float $liquidation_threshold
 * @property float $liquidation_penalty
 * @property float $total_supply
 * @property float $max_supply
 * @property float $total_collateral_value
 * @property float $mint_fee
 * @property float $burn_fee
 * @property int $precision
 * @property bool $is_active
 * @property bool $minting_enabled
 * @property bool $burning_enabled
 * @property array $metadata
 * @property string $uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
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
 * @method static static|null findOrFail(mixed $id, array $columns = ['*'])
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
class Stablecoin extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'code';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'peg_asset_code',
        'peg_ratio',
        'target_price',
        'stability_mechanism',
        'collateral_ratio',
        'min_collateral_ratio',
        'liquidation_penalty',
        'total_supply',
        'max_supply',
        'total_collateral_value',
        'mint_fee',
        'burn_fee',
        'precision',
        'is_active',
        'minting_enabled',
        'burning_enabled',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'peg_ratio'            => 'decimal:8',
        'target_price'         => 'decimal:8',
        'collateral_ratio'     => 'decimal:4',
        'min_collateral_ratio' => 'decimal:4',
        'liquidation_penalty'  => 'decimal:4',
        'mint_fee'             => 'decimal:6',
        'burn_fee'             => 'decimal:6',
        'is_active'            => 'boolean',
        'minting_enabled'      => 'boolean',
        'burning_enabled'      => 'boolean',
        'metadata'             => 'array',
    ];

    /**
     * Get the asset this stablecoin is pegged to.
     */
    public function pegAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'peg_asset_code', 'code');
    }

    /**
     * Get all collateral positions for this stablecoin.
     */
    public function collateralPositions(): HasMany
    {
        return $this->hasMany(StablecoinCollateralPosition::class, 'stablecoin_code', 'code');
    }

    /**
     * Get active collateral positions.
     */
    /**
     * @return HasMany
     */
    public function activePositions(): HasMany
    {
        return $this->collateralPositions()->where('status', 'active');
    }

    /**
     * Check if minting is currently allowed.
     */
    public function canMint(): bool
    {
        return $this->is_active && $this->minting_enabled;
    }

    /**
     * Check if burning is currently allowed.
     */
    public function canBurn(): bool
    {
        return $this->is_active && $this->burning_enabled;
    }

    /**
     * Check if the total supply limit has been reached.
     */
    public function hasReachedMaxSupply(): bool
    {
        return $this->max_supply !== null && $this->total_supply >= $this->max_supply;
    }

    /**
     * Calculate the current global collateralization ratio.
     */
    public function calculateGlobalCollateralizationRatio(): float
    {
        if ($this->total_supply == 0) {
            return 0;
        }

        return $this->total_collateral_value / $this->total_supply;
    }

    /**
     * Check if the stablecoin is adequately collateralized.
     */
    public function isAdequatelyCollateralized(): bool
    {
        return $this->calculateGlobalCollateralizationRatio() >= $this->min_collateral_ratio;
    }

    /**
     * Get the total value of all collateral in the peg asset.
     */
    public function getTotalCollateralValueInPegAsset(): int
    {
        // This would need to be calculated based on current exchange rates
        // For now, assuming all collateral is already in peg asset terms
        return (int) $this->total_collateral_value;
    }

    /**
     * Scope to only active stablecoins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to stablecoins where minting is enabled.
     */
    public function scopeMintingEnabled($query)
    {
        return $query->where('minting_enabled', true);
    }

    /**
     * Scope to stablecoins where burning is enabled.
     */
    public function scopeBurningEnabled($query)
    {
        return $query->where('burning_enabled', true);
    }

    /**
     * Scope to filter by stability mechanism.
     */
    public function scopeByMechanism($query, string $mechanism)
    {
        return $query->where('stability_mechanism', $mechanism);
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
