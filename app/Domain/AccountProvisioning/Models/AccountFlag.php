<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountFlag extends Model
{
    protected $table = 'account_flags';

    protected $fillable = [
        'user_id', 'is_review_account', 'bypass_device_attestation',
        'bypass_rate_limit', 'bypass_sanctions_screening', 'bypass_sms_otp',
        'suppress_notifications', 'kyc_override_level', 'note',
        'expires_at', 'created_by', 'disabled_at',
    ];

    protected $casts = [
        'is_review_account'          => 'bool',
        'bypass_device_attestation'  => 'bool',
        'bypass_rate_limit'          => 'bool',
        'bypass_sanctions_screening' => 'bool',
        'bypass_sms_otp'             => 'bool',
        'suppress_notifications'     => 'bool',
        'kyc_override_level'         => 'int',
        'expires_at'                 => 'datetime',
        'disabled_at'                => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        if (! $this->is_review_account) {
            return false;
        }

        if ($this->disabled_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
