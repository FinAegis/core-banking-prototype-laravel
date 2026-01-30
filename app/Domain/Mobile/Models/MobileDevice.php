<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mobile device model for tracking registered mobile app installations.
 *
 * @property string $id
 * @property int $user_id
 * @property string $device_id
 * @property string $platform
 * @property string|null $push_token
 * @property string|null $device_name
 * @property string|null $device_model
 * @property string|null $os_version
 * @property string $app_version
 * @property bool $biometric_enabled
 * @property string|null $biometric_public_key
 * @property string|null $biometric_key_id
 * @property \Carbon\Carbon|null $last_active_at
 * @property \Carbon\Carbon|null $biometric_enabled_at
 * @property int $biometric_failure_count
 * @property \Carbon\Carbon|null $biometric_blocked_until
 * @property bool $is_trusted
 * @property \Carbon\Carbon|null $trusted_at
 * @property string|null $trusted_by
 * @property bool $is_blocked
 * @property \Carbon\Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withPushToken()
 * @method static \Illuminate\Database\Eloquent\Builder<static> biometricEnabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static> forPlatform(string $platform)
 */
class MobileDevice extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'push_token',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'biometric_enabled',
        'biometric_public_key',
        'biometric_key_id',
        'last_active_at',
        'biometric_enabled_at',
        'biometric_failure_count',
        'biometric_blocked_until',
        'is_trusted',
        'trusted_at',
        'trusted_by',
        'is_blocked',
        'blocked_at',
        'blocked_reason',
        'metadata',
    ];

    protected $casts = [
        'biometric_enabled'       => 'boolean',
        'is_trusted'              => 'boolean',
        'is_blocked'              => 'boolean',
        'last_active_at'          => 'datetime',
        'biometric_enabled_at'    => 'datetime',
        'biometric_failure_count' => 'integer',
        'biometric_blocked_until' => 'datetime',
        'trusted_at'              => 'datetime',
        'blocked_at'              => 'datetime',
        'metadata'                => 'array',
    ];

    protected $hidden = [
        'biometric_public_key',
    ];

    /**
     * Get the user that owns this device.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sessions for this device.
     *
     * @return HasMany<MobileDeviceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(MobileDeviceSession::class);
    }

    /**
     * Get the active sessions for this device.
     *
     * @return HasMany<MobileDeviceSession, $this>
     */
    public function activeSessions(): HasMany
    {
        return $this->sessions()->where('expires_at', '>', now());
    }

    /**
     * Get the push notifications for this device.
     *
     * @return HasMany<MobilePushNotification, $this>
     */
    public function pushNotifications(): HasMany
    {
        return $this->hasMany(MobilePushNotification::class);
    }

    /**
     * Get the biometric challenges for this device.
     *
     * @return HasMany<BiometricChallenge, $this>
     */
    public function biometricChallenges(): HasMany
    {
        return $this->hasMany(BiometricChallenge::class);
    }

    /**
     * Get the biometric authentication failures for this device.
     *
     * @return HasMany<BiometricFailure, $this>
     */
    public function biometricFailures(): HasMany
    {
        return $this->hasMany(BiometricFailure::class);
    }

    /**
     * Scope to get only active (non-blocked) devices.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_blocked', false);
    }

    /**
     * Scope to get devices with push tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithPushToken(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('push_token');
    }

    /**
     * Scope to get devices with biometric enabled.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeBiometricEnabled(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('biometric_enabled', true);
    }

    /**
     * Scope to filter by platform.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPlatform(\Illuminate\Database\Eloquent\Builder $query, string $platform): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Check if the device can receive push notifications.
     */
    public function canReceivePush(): bool
    {
        return ! $this->is_blocked && $this->push_token !== null;
    }

    /**
     * Check if biometric authentication is available.
     */
    public function canUseBiometric(): bool
    {
        return ! $this->is_blocked
            && ! $this->isBiometricBlocked()
            && $this->biometric_enabled
            && $this->biometric_public_key !== null;
    }

    /**
     * Check if biometric authentication is temporarily blocked.
     */
    public function isBiometricBlocked(): bool
    {
        return $this->biometric_blocked_until !== null
            && $this->biometric_blocked_until->isFuture();
    }

    /**
     * Block biometric authentication temporarily.
     */
    public function blockBiometric(int $minutes = 30): void
    {
        $this->update([
            'biometric_blocked_until' => now()->addMinutes($minutes),
            'biometric_failure_count' => 0,
        ]);
    }

    /**
     * Unblock biometric authentication.
     */
    public function unblockBiometric(): void
    {
        $this->update([
            'biometric_blocked_until' => null,
            'biometric_failure_count' => 0,
        ]);
    }

    /**
     * Increment the biometric failure count.
     */
    public function incrementBiometricFailures(): void
    {
        $this->increment('biometric_failure_count');
    }

    /**
     * Reset the biometric failure count (on successful auth).
     */
    public function resetBiometricFailures(): void
    {
        $this->update(['biometric_failure_count' => 0]);
    }

    /**
     * Update the last activity timestamp.
     */
    public function recordActivity(): bool
    {
        $this->last_active_at = now();

        return $this->save();
    }

    /**
     * Enable biometric authentication for this device.
     */
    public function enableBiometric(string $publicKey, string $keyId): void
    {
        $this->update([
            'biometric_enabled'    => true,
            'biometric_public_key' => $publicKey,
            'biometric_key_id'     => $keyId,
            'biometric_enabled_at' => now(),
        ]);
    }

    /**
     * Disable biometric authentication for this device.
     */
    public function disableBiometric(): void
    {
        $this->update([
            'biometric_enabled'    => false,
            'biometric_public_key' => null,
            'biometric_key_id'     => null,
        ]);
    }

    /**
     * Block this device.
     */
    public function block(string $reason): void
    {
        $this->update([
            'is_blocked'     => true,
            'blocked_at'     => now(),
            'blocked_reason' => $reason,
        ]);

        // Invalidate all sessions
        $this->sessions()->delete();
    }

    /**
     * Unblock this device.
     */
    public function unblock(): void
    {
        $this->update([
            'is_blocked'     => false,
            'blocked_at'     => null,
            'blocked_reason' => null,
        ]);
    }

    /**
     * Mark device as trusted.
     */
    public function trust(?string $trustedBy = null): void
    {
        $this->update([
            'is_trusted' => true,
            'trusted_at' => now(),
            'trusted_by' => $trustedBy,
        ]);
    }

    /**
     * Update the push token.
     */
    public function updatePushToken(string $token): void
    {
        $this->update(['push_token' => $token]);
    }

    /**
     * Get a display-friendly name for this device.
     */
    public function getDisplayName(): string
    {
        if ($this->device_name) {
            return $this->device_name;
        }

        if ($this->device_model) {
            return $this->device_model;
        }

        return ucfirst($this->platform) . ' Device';
    }
}
