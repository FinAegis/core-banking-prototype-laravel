<?php

namespace App\Domain\Stablecoin\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
class StablecoinCollateralPosition extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'account_uuid',
        'stablecoin_code',
        'collateral_asset_code',
        'collateral_amount',
        'debt_amount',
        'collateral_ratio',
        'liquidation_price',
        'interest_accrued',
        'status',
        'last_interaction_at',
        'liquidated_at',
        'auto_liquidation_enabled',
        'stop_loss_ratio',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'collateral_amount'        => 'integer',
        'debt_amount'              => 'integer',
        'interest_accrued'         => 'integer',
        'collateral_ratio'         => 'decimal:4',
        'liquidation_price'        => 'decimal:8',
        'stop_loss_ratio'          => 'decimal:4',
        'last_interaction_at'      => 'datetime',
        'liquidated_at'            => 'datetime',
        'auto_liquidation_enabled' => 'boolean',
    ];

    /**
     * Get the account that owns this position.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    /**
     * Get the stablecoin this position is for.
     */
    public function stablecoin(): BelongsTo
    {
        return $this->belongsTo(Stablecoin::class, 'stablecoin_code', 'code');
    }

    /**
     * Get the collateral asset.
     */
    public function collateralAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'collateral_asset_code', 'code');
    }

    /**
     * Check if this position is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if this position is at risk of liquidation.
     */
    public function isAtRiskOfLiquidation(): bool
    {
        return $this->collateral_ratio <= $this->stablecoin->min_collateral_ratio;
    }

    /**
     * Check if position should be auto-liquidated.
     */
    public function shouldAutoLiquidate(): bool
    {
        if (! $this->auto_liquidation_enabled || ! $this->isActive()) {
            return false;
        }

        // Check against minimum collateral ratio
        if ($this->collateral_ratio <= $this->stablecoin->min_collateral_ratio) {
            return true;
        }

        // Check against stop loss ratio if set
        if ($this->stop_loss_ratio && $this->collateral_ratio <= $this->stop_loss_ratio) {
            return true;
        }

        return false;
    }

    /**
     * Scope to get positions that should be auto-liquidated.
     */
    public function scopeShouldAutoLiquidate($query)
    {
        return $query->where('status', 'active')
            ->where('auto_liquidation_enabled', true)
            ->where(
                function ($q) {
                    $q->whereHas(
                        'stablecoin',
                        function ($sq) {
                            $sq->whereColumn('stablecoin_collateral_positions.collateral_ratio', '<=', 'stablecoins.min_collateral_ratio');
                        }
                    )
                    ->orWhereNotNull('stop_loss_ratio')
                    ->whereColumn('collateral_ratio', '<=', 'stop_loss_ratio');
                }
            );
    }

    /**
     * Calculate the maximum amount of stablecoin that can be minted.
     */
    public function calculateMaxMintAmount(): int
    {
        $collateralValueInPegAsset = $this->getCollateralValueInPegAsset();
        $maxDebt = $collateralValueInPegAsset / $this->stablecoin->collateral_ratio;

        return max(0, (int) ($maxDebt - $this->debt_amount));
    }

    /**
     * Calculate current collateral value in the peg asset.
     */
    public function getCollateralValueInPegAsset(): int
    {
        // This would need exchange rate conversion
        // For now, assuming direct conversion or same asset
        if ($this->collateral_asset_code === $this->stablecoin->peg_asset_code) {
            return $this->collateral_amount;
        }

        // Would need to use exchange rate service here
        // Return collateral amount for now
        return $this->collateral_amount;
    }

    /**
     * Calculate the liquidation price for this position.
     */
    public function calculateLiquidationPrice(): float
    {
        if ($this->debt_amount == 0) {
            return 0;
        }

        // Liquidation price = (debt * min_collateral_ratio) / collateral_amount
        return ($this->debt_amount * $this->stablecoin->min_collateral_ratio) / $this->collateral_amount;
    }

    /**
     * Update the collateral ratio based on current values.
     */
    public function updateCollateralRatio(): void
    {
        if ($this->debt_amount == 0) {
            $this->collateral_ratio = 0;
        } else {
            $collateralValue = $this->getCollateralValueInPegAsset();
            $this->collateral_ratio = $collateralValue / $this->debt_amount;
        }

        $this->liquidation_price = $this->calculateLiquidationPrice();
        $this->save();
    }

    /**
     * Mark position as liquidated.
     */
    public function markAsLiquidated(): void
    {
        $this->update(
            [
            'status'        => 'liquidated',
            'liquidated_at' => now(),
            ]
        );
    }

    /**
     * Scope to active positions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to positions at risk of liquidation.
     */
    public function scopeAtRisk($query)
    {
        return $query->whereIn('uuid', function ($subquery) {
            $subquery->select('scp.uuid')
                ->from('stablecoin_collateral_positions as scp')
                ->join('stablecoins as s', 'scp.stablecoin_code', '=', 's.code')
                ->whereColumn('scp.collateral_ratio', '<=', 's.min_collateral_ratio');
        });
    }

    /**
     * Scope to positions that should be auto-liquidated.
     */
    public function scopeAutoLiquidatable($query)
    {
        return $query->where('auto_liquidation_enabled', true)
            ->where('status', 'active')
            ->where(
                function ($q) {
                    $q->whereIn('uuid', function ($subquery) {
                        $subquery->select('scp.uuid')
                            ->from('stablecoin_collateral_positions as scp')
                            ->join('stablecoins as s', 'scp.stablecoin_code', '=', 's.code')
                            ->whereColumn('scp.collateral_ratio', '<=', 's.min_collateral_ratio');
                    })
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('stop_loss_ratio')
                            ->whereColumn('collateral_ratio', '<=', 'stop_loss_ratio');
                    });
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
