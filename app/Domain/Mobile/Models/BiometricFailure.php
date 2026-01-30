<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks biometric authentication failures for security rate limiting.
 *
 * Each failed biometric authentication attempt is recorded to enable
 * per-device rate limiting and automatic blocking of suspicious devices.
 *
 * @property string $id
 * @property string $mobile_device_id
 * @property string|null $ip_address
 * @property string $failure_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> recentForDevice(string $deviceId, int $minutes = 10)
 */
class BiometricFailure extends Model
{
    use HasUuids;

    /**
     * Failure reason constants.
     */
    public const REASON_SIGNATURE_INVALID = 'signature_invalid';

    public const REASON_CHALLENGE_EXPIRED = 'challenge_expired';

    public const REASON_CHALLENGE_NOT_FOUND = 'challenge_not_found';

    public const REASON_IP_MISMATCH = 'ip_mismatch';

    public const REASON_USER_AGENT_INVALID = 'user_agent_invalid';

    public const REASON_DEVICE_BLOCKED = 'device_blocked';

    protected $fillable = [
        'mobile_device_id',
        'ip_address',
        'failure_reason',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the device this failure belongs to.
     *
     * @return BelongsTo<MobileDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class, 'mobile_device_id');
    }

    /**
     * Scope to get recent failures for a device.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRecentForDevice(
        \Illuminate\Database\Eloquent\Builder $query,
        string $deviceId,
        int $minutes = 10
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->where('mobile_device_id', $deviceId)
            ->where('created_at', '>', now()->subMinutes($minutes));
    }

    /**
     * Get the count of recent failures for a device.
     */
    public static function countRecentForDevice(string $deviceId, int $minutes = 10): int
    {
        return static::recentForDevice($deviceId, $minutes)->count();
    }

    /**
     * Record a new failure.
     */
    public static function record(
        string $deviceId,
        string $reason,
        ?string $ipAddress = null
    ): self {
        /** @var self $failure */
        $failure = static::create([
            'mobile_device_id' => $deviceId,
            'failure_reason'   => $reason,
            'ip_address'       => $ipAddress,
        ]);

        return $failure;
    }

    /**
     * Cleanup old failures beyond the retention period.
     */
    public static function cleanup(int $days = 7): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
