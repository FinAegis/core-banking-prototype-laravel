<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Wallet\ValueObjects\HardwareWalletDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hardware wallet association model.
 *
 * @property string $id
 * @property int $user_id
 * @property string $device_type
 * @property string $device_id
 * @property string|null $device_label
 * @property string|null $firmware_version
 * @property string $public_key
 * @property string|null $address
 * @property string $chain
 * @property string $derivation_path
 * @property array<string> $supported_chains
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property bool $is_verified
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PendingSigningRequest> $signingRequests
 */
class HardwareWalletAssociation extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'hardware_wallet_associations';

    protected $fillable = [
        'user_id',
        'device_type',
        'device_id',
        'device_label',
        'firmware_version',
        'public_key',
        'address',
        'chain',
        'derivation_path',
        'supported_chains',
        'metadata',
        'is_active',
        'is_verified',
        'verified_at',
        'last_used_at',
    ];

    protected $casts = [
        'supported_chains' => 'array',
        'metadata'         => 'array',
        'is_active'        => 'boolean',
        'is_verified'      => 'boolean',
        'verified_at'      => 'datetime',
        'last_used_at'     => 'datetime',
    ];

    /**
     * Get the user that owns this hardware wallet association.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the signing requests for this association.
     *
     * @return HasMany<PendingSigningRequest, $this>
     */
    public function signingRequests(): HasMany
    {
        return $this->hasMany(PendingSigningRequest::class, 'association_id');
    }

    /**
     * Scope to get active associations.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get associations for a specific chain.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForChain(Builder $query, string $chain): Builder
    {
        return $query->where('chain', $chain);
    }

    /**
     * Scope to get associations by device type.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByDeviceType(Builder $query, string $deviceType): Builder
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope to get Ledger devices.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLedger(Builder $query): Builder
    {
        return $query->whereIn('device_type', [
            HardwareWalletDevice::TYPE_LEDGER_NANO_S,
            HardwareWalletDevice::TYPE_LEDGER_NANO_X,
        ]);
    }

    /**
     * Scope to get Trezor devices.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTrezor(Builder $query): Builder
    {
        return $query->whereIn('device_type', [
            HardwareWalletDevice::TYPE_TREZOR_ONE,
            HardwareWalletDevice::TYPE_TREZOR_MODEL_T,
        ]);
    }

    /**
     * Check if this is a Ledger device.
     */
    public function isLedger(): bool
    {
        return in_array($this->device_type, [
            HardwareWalletDevice::TYPE_LEDGER_NANO_S,
            HardwareWalletDevice::TYPE_LEDGER_NANO_X,
        ], true);
    }

    /**
     * Check if this is a Trezor device.
     */
    public function isTrezor(): bool
    {
        return in_array($this->device_type, [
            HardwareWalletDevice::TYPE_TREZOR_ONE,
            HardwareWalletDevice::TYPE_TREZOR_MODEL_T,
        ], true);
    }

    /**
     * Mark the association as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Update the last used timestamp.
     */
    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate this association.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Reactivate this association.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Convert to HardwareWalletDevice value object.
     */
    public function toValueObject(): HardwareWalletDevice
    {
        return HardwareWalletDevice::fromArray([
            'type'             => $this->device_type,
            'device_id'        => $this->device_id,
            'label'            => $this->device_label ?? '',
            'firmware_version' => $this->firmware_version ?? '',
            'supported_chains' => $this->supported_chains ?? [],
            'public_key'       => $this->public_key,
            'address'          => $this->address,
            'metadata'         => $this->metadata ?? [],
        ]);
    }
}
