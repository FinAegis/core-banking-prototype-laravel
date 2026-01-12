<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * StablecoinReserveAuditLog - Tracks all reserve movements and changes.
 *
 * @property int $id
 * @property string $audit_id
 * @property string $reserve_id
 * @property string $pool_id
 * @property string $stablecoin_code
 * @property string $asset_code
 * @property string $action
 * @property string $amount_change
 * @property string $amount_before
 * @property string $amount_after
 * @property string|null $value_usd_before
 * @property string|null $value_usd_after
 * @property string|null $transaction_hash
 * @property string|null $custodian_id
 * @property string|null $executed_by
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $executed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static> forReserve(string $reserveId)
 * @method static \Illuminate\Database\Eloquent\Builder<static> forAction(string $action)
 * @method static static|null find(mixed $id)
 * @method static static|null first()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Collection<int, static> get()
 */
class StablecoinReserveAuditLog extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'stablecoin_reserve_audit_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'audit_id',
        'reserve_id',
        'pool_id',
        'stablecoin_code',
        'asset_code',
        'action',
        'amount_change',
        'amount_before',
        'amount_after',
        'value_usd_before',
        'value_usd_after',
        'transaction_hash',
        'custodian_id',
        'executed_by',
        'reason',
        'metadata',
        'executed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount_change'    => 'decimal:18',
        'amount_before'    => 'decimal:18',
        'amount_after'     => 'decimal:18',
        'value_usd_before' => 'decimal:8',
        'value_usd_after'  => 'decimal:8',
        'metadata'         => 'array',
        'executed_at'      => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StablecoinReserveAuditLog $log): void {
            if (empty($log->audit_id)) {
                $log->audit_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the reserve this log belongs to.
     *
     * @return BelongsTo<StablecoinReserve, $this>
     */
    public function reserve(): BelongsTo
    {
        return $this->belongsTo(StablecoinReserve::class, 'reserve_id', 'reserve_id');
    }

    /**
     * Scope to filter by reserve.
     *
     * @param \Illuminate\Database\Eloquent\Builder<StablecoinReserveAuditLog> $query
     * @return \Illuminate\Database\Eloquent\Builder<StablecoinReserveAuditLog>
     */
    public function scopeForReserve($query, string $reserveId)
    {
        return $query->where('reserve_id', $reserveId);
    }

    /**
     * Scope to filter by action type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<StablecoinReserveAuditLog> $query
     * @return \Illuminate\Database\Eloquent\Builder<StablecoinReserveAuditLog>
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Check if this was a deposit.
     */
    public function isDeposit(): bool
    {
        return $this->action === 'deposit';
    }

    /**
     * Check if this was a withdrawal.
     */
    public function isWithdrawal(): bool
    {
        return $this->action === 'withdrawal';
    }

    /**
     * Get the absolute amount change.
     */
    public function getAbsoluteAmountChange(): string
    {
        /** @var numeric-string $change */
        $change = (string) $this->amount_change;
        if (bccomp($change, '0', 18) < 0) {
            return bcmul($change, '-1', 18);
        }

        return $change;
    }

    /**
     * Get the USD value change.
     */
    public function getValueUsdChange(): ?string
    {
        if ($this->value_usd_before === null || $this->value_usd_after === null) {
            return null;
        }

        /** @var numeric-string $after */
        $after = (string) $this->value_usd_after;
        /** @var numeric-string $before */
        $before = (string) $this->value_usd_before;

        return bcsub($after, $before, 8);
    }
}
