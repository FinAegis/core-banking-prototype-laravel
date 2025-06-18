<?php

namespace App\Models;

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'peg_ratio' => 'decimal:8',
        'target_price' => 'decimal:8',
        'collateral_ratio' => 'decimal:4',
        'min_collateral_ratio' => 'decimal:4',
        'liquidation_penalty' => 'decimal:4',
        'mint_fee' => 'decimal:6',
        'burn_fee' => 'decimal:6',
        'is_active' => 'boolean',
        'minting_enabled' => 'boolean',
        'burning_enabled' => 'boolean',
        'metadata' => 'array',
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
        return $this->total_collateral_value;
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
}
