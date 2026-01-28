<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * StablecoinReserve read model - Projects reserve data from ReservePool aggregate.
 *
 * @property int $id
 * @property string $reserve_id
 * @property string $pool_id
 * @property string $stablecoin_code
 * @property string $asset_code
 * @property string $amount
 * @property string $value_usd
 * @property string $allocation_percentage
 * @property string|null $custodian_id
 * @property string|null $custodian_name
 * @property string $custodian_type
 * @property string|null $wallet_address
 * @property \Illuminate\Support\Carbon|null $last_verified_at
 * @property string|null $verification_source
 * @property string|null $verification_tx_hash
 * @property array<string, mixed>|null $verification_metadata
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereIn(string $column, mixed $values)
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 * @method static \Illuminate\Database\Eloquent\Builder<static> forStablecoin(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder<static> forPool(string $poolId)
 * @method static static|null find(mixed $id)
 * @method static static|null first()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Collection<int, static> get()
 */
class StablecoinReserve extends Model
{
    use UsesTenantConnection;
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'stablecoin_reserves';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reserve_id',
        'pool_id',
        'stablecoin_code',
        'asset_code',
        'amount',
        'value_usd',
        'allocation_percentage',
        'custodian_id',
        'custodian_name',
        'custodian_type',
        'wallet_address',
        'last_verified_at',
        'verification_source',
        'verification_tx_hash',
        'verification_metadata',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount'                => 'decimal:18',
        'value_usd'             => 'decimal:8',
        'allocation_percentage' => 'decimal:4',
        'last_verified_at'      => 'datetime',
        'verification_metadata' => 'array',
        'metadata'              => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StablecoinReserve $reserve): void {
            if (empty($reserve->reserve_id)) {
                $reserve->reserve_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the stablecoin this reserve belongs to.
     *
     * @return BelongsTo<Stablecoin, $this>
     */
    public function stablecoin(): BelongsTo
    {
        return $this->belongsTo(Stablecoin::class, 'stablecoin_code', 'code');
    }

    /**
     * Get the audit logs for this reserve.
     *
     * @return HasMany<StablecoinReserveAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(StablecoinReserveAuditLog::class, 'reserve_id', 'reserve_id');
    }

    /**
     * Scope to active reserves only.
     *
     * @param \Illuminate\Database\Eloquent\Builder<StablecoinReserve> $query
     * @return \Illuminate\Database\Eloquent\Builder<StablecoinReserve>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by stablecoin.
     *
     * @param \Illuminate\Database\Eloquent\Builder<StablecoinReserve> $query
     * @return \Illuminate\Database\Eloquent\Builder<StablecoinReserve>
     */
    public function scopeForStablecoin($query, string $code)
    {
        return $query->where('stablecoin_code', $code);
    }

    /**
     * Scope to filter by pool.
     *
     * @param \Illuminate\Database\Eloquent\Builder<StablecoinReserve> $query
     * @return \Illuminate\Database\Eloquent\Builder<StablecoinReserve>
     */
    public function scopeForPool($query, string $poolId)
    {
        return $query->where('pool_id', $poolId);
    }

    /**
     * Check if the reserve is verified recently.
     */
    public function isRecentlyVerified(int $hoursThreshold = 24): bool
    {
        if ($this->last_verified_at === null) {
            return false;
        }

        return $this->last_verified_at->diffInHours(now()) < $hoursThreshold;
    }

    /**
     * Get the amount as a float for calculations.
     */
    public function getAmountAsFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Get the USD value as a float for calculations.
     */
    public function getValueUsdAsFloat(): float
    {
        return (float) $this->value_usd;
    }

    /**
     * Update the reserve amount and log the change.
     */
    public function updateAmount(
        string $newAmount,
        string $action,
        ?string $transactionHash = null,
        ?string $executedBy = null,
        ?string $reason = null
    ): void {
        /** @var numeric-string $amountBefore */
        $amountBefore = (string) $this->amount;
        /** @var numeric-string $newAmountNumeric */
        $newAmountNumeric = $newAmount;
        $amountChange = bcsub($newAmountNumeric, $amountBefore, 18);

        $this->amount = $newAmount;
        $this->save();

        StablecoinReserveAuditLog::create([
            'reserve_id'       => $this->reserve_id,
            'pool_id'          => $this->pool_id,
            'stablecoin_code'  => $this->stablecoin_code,
            'asset_code'       => $this->asset_code,
            'action'           => $action,
            'amount_change'    => $amountChange,
            'amount_before'    => $amountBefore,
            'amount_after'     => $newAmount,
            'value_usd_before' => $this->value_usd,
            'value_usd_after'  => $this->value_usd, // Will be updated separately
            'transaction_hash' => $transactionHash,
            'custodian_id'     => $this->custodian_id,
            'executed_by'      => $executedBy,
            'reason'           => $reason,
            'executed_at'      => now(),
        ]);
    }

    /**
     * Update the USD value based on current price.
     */
    public function updateValueUsd(float $pricePerUnit, ?string $source = null): void
    {
        $valueBefore = $this->value_usd;
        /** @var numeric-string $amount */
        $amount = (string) $this->amount;
        $price = number_format($pricePerUnit, 8, '.', '');
        $newValue = bcmul($amount, $price, 8);

        $this->value_usd = $newValue;
        $this->save();

        StablecoinReserveAuditLog::create([
            'reserve_id'       => $this->reserve_id,
            'pool_id'          => $this->pool_id,
            'stablecoin_code'  => $this->stablecoin_code,
            'asset_code'       => $this->asset_code,
            'action'           => 'price_update',
            'amount_change'    => '0',
            'amount_before'    => $this->amount,
            'amount_after'     => $this->amount,
            'value_usd_before' => $valueBefore,
            'value_usd_after'  => $newValue,
            'executed_by'      => 'system',
            'reason'           => "Price update from {$source}",
            'executed_at'      => now(),
            'metadata'         => ['price_per_unit' => $pricePerUnit, 'source' => $source],
        ]);
    }

    /**
     * Mark the reserve as verified.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function markVerified(string $source, ?string $txHash = null, ?array $metadata = null): void
    {
        $this->last_verified_at = now();
        $this->verification_source = $source;
        $this->verification_tx_hash = $txHash;
        $this->verification_metadata = $metadata;
        $this->save();

        StablecoinReserveAuditLog::create([
            'reserve_id'       => $this->reserve_id,
            'pool_id'          => $this->pool_id,
            'stablecoin_code'  => $this->stablecoin_code,
            'asset_code'       => $this->asset_code,
            'action'           => 'verification',
            'amount_change'    => '0',
            'amount_before'    => $this->amount,
            'amount_after'     => $this->amount,
            'transaction_hash' => $txHash,
            'executed_by'      => 'system',
            'reason'           => "Verification from {$source}",
            'executed_at'      => now(),
            'metadata'         => $metadata,
        ]);
    }
}
