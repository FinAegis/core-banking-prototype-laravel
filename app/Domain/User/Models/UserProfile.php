<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use UsesTenantConnection;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'email',
        'first_name',
        'last_name',
        'phone_number',
        'date_of_birth',
        'country',
        'city',
        'address',
        'postal_code',
        'status',
        'is_verified',
        'preferences',
        'notification_preferences',
        'privacy_settings',
        'suspended_at',
        'suspension_reason',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'is_verified'              => 'boolean',
        'preferences'              => 'array',
        'notification_preferences' => 'array',
        'privacy_settings'         => 'array',
        'metadata'                 => 'array',
        'date_of_birth'            => 'date',
        'suspended_at'             => 'datetime',
        'last_activity_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function getFullNameAttribute(): ?string
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isDeleted(): bool
    {
        return $this->status === 'deleted';
    }
}
