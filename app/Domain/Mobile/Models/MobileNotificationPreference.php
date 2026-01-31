<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mobile notification preference model.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $mobile_device_id
 * @property string $notification_type
 * @property bool $push_enabled
 * @property bool $email_enabled
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MobileNotificationPreference extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Available notification types.
     */
    public const TYPE_TRANSACTION_RECEIVED = 'transaction_received';

    public const TYPE_TRANSACTION_SENT = 'transaction_sent';

    public const TYPE_LOW_BALANCE = 'low_balance';

    public const TYPE_SECURITY_LOGIN = 'security_login';

    public const TYPE_SECURITY_DEVICE = 'security_device';

    public const TYPE_MARKETING = 'marketing';

    public const TYPE_SYSTEM_UPDATE = 'system_update';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'mobile_device_id',
        'notification_type',
        'push_enabled',
        'email_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'push_enabled'  => 'boolean',
        'email_enabled' => 'boolean',
    ];

    /**
     * Get the user that owns this preference.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mobile device this preference belongs to, if device-specific.
     *
     * @return BelongsTo<MobileDevice, $this>
     */
    public function mobileDevice(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class);
    }

    /**
     * Get all available notification types.
     *
     * @return array<string, string>
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_TRANSACTION_RECEIVED => 'Transaction received notifications',
            self::TYPE_TRANSACTION_SENT     => 'Transaction sent notifications',
            self::TYPE_LOW_BALANCE          => 'Low balance alerts',
            self::TYPE_SECURITY_LOGIN       => 'New login notifications',
            self::TYPE_SECURITY_DEVICE      => 'Device security notifications',
            self::TYPE_MARKETING            => 'Marketing and promotional messages',
            self::TYPE_SYSTEM_UPDATE        => 'System updates and announcements',
        ];
    }

    /**
     * Get default preferences for a new user.
     *
     * @return array<string, array{push_enabled: bool, email_enabled: bool}>
     */
    public static function getDefaults(): array
    {
        return [
            self::TYPE_TRANSACTION_RECEIVED => ['push_enabled' => true, 'email_enabled' => false],
            self::TYPE_TRANSACTION_SENT     => ['push_enabled' => true, 'email_enabled' => false],
            self::TYPE_LOW_BALANCE          => ['push_enabled' => true, 'email_enabled' => true],
            self::TYPE_SECURITY_LOGIN       => ['push_enabled' => true, 'email_enabled' => true],
            self::TYPE_SECURITY_DEVICE      => ['push_enabled' => true, 'email_enabled' => true],
            self::TYPE_MARKETING            => ['push_enabled' => false, 'email_enabled' => false],
            self::TYPE_SYSTEM_UPDATE        => ['push_enabled' => true, 'email_enabled' => false],
        ];
    }
}
